<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Session;
use App\Models\AuditLog;
use App\Models\FeatureFlag;
use App\Models\SupervisorHabilitation;
use App\Models\Supervisor;

/**
 * Gestion des superviseurs et authentification de bypass.
 *
 * Deux rôles :
 *  – Modération  : CRUD des comptes superviseurs (/moderation/supervisors/*)
 *  – Public/auth : Formulaire d'auth de bypass (/supervisor/bypass/*)
 */
class SupervisorController extends Controller
{
    public const ACCOUNT_CONTROL_STEPUP_KEY = 'moderation.account_control_stepup';
    public const EVENT_TYCOON_DEV_MODE_KEY = 'event.tycoon_dev_mode';

    private Supervisor $supervisorModel;
    private SupervisorHabilitation $habilitationModel;

    public function __construct()
    {
        $this->supervisorModel = new Supervisor();
        $this->habilitationModel = new SupervisorHabilitation();
    }

    // ─────────────────────────────────────────────────────────────────────────
    // MODÉRATION — Gestion des superviseurs
    // ─────────────────────────────────────────────────────────────────────────

    /** Liste tous les superviseurs. */
    public function index(): void
    {
        $this->requireModerator();

        $supervisors = $this->supervisorModel->findAll('created_at', 'DESC');
        $assignableHabilitations = $this->getAssignableHabilitations();

        foreach ($supervisors as &$supervisor) {
            $supervisor['habilitations'] = $this->habilitationModel->getAuthorizationsForSupervisor((int) $supervisor['id']);
        }
        unset($supervisor);

        $this->render('moderation/supervisors/index', [
            'title'                  => 'Modération — Superviseurs',
            'supervisors'             => $supervisors,
            'assignableHabilitations'  => $assignableHabilitations,
        ]);
    }

    /** Formulaire de création. */
    public function create(): void
    {
        $this->requireModerator();
        $this->render('moderation/supervisors/form', [
            'title'      => 'Nouveau superviseur',
            'supervisor' => null,
            'isEditMode' => false,
            'currentHabilitations' => [],
            'assignableHabilitations' => $this->getAssignableHabilitations(),
        ]);
    }

    public function editHabilitations(string $id): void
    {
        $this->requireModerator();

        $supervisor = $this->supervisorModel->find((int) $id);
        if (!$supervisor) {
            $this->setFlash('danger', 'Superviseur introuvable.');
            $this->redirect('/moderation/supervisors');
            return;
        }

        $currentHabilitations = array_column(
            $this->habilitationModel->getAuthorizationsForSupervisor((int) $id),
            'feature_key'
        );

        $this->render('moderation/supervisors/form', [
            'title'                  => 'Habilitations du superviseur',
            'supervisor'             => $supervisor,
            'isEditMode'             => true,
            'currentHabilitations'   => $currentHabilitations,
            'assignableHabilitations'=> $this->getAssignableHabilitations(),
        ]);
    }

    public function updateHabilitations(string $id): void
    {
        $this->requireModerator();
        $this->validateCSRF();

        $supervisor = $this->supervisorModel->find((int) $id);
        if (!$supervisor) {
            $this->setFlash('danger', 'Superviseur introuvable.');
            $this->redirect('/moderation/supervisors');
            return;
        }

        $selected = $_POST['habilitations'] ?? [];
        if (!is_array($selected)) {
            $selected = [];
        }

        $allowedKeys = array_keys($this->getAssignableHabilitations());
        $selected = array_values(array_intersect($allowedKeys, array_map('strval', $selected)));

        $this->habilitationModel->syncAuthorizations((int) $id, $selected);

        AuditLog::log(
            (int) $this->getCurrentUserId(),
            AuditLog::ACTION_SUPERVISOR_HABILITATION_UPDATE,
            [
                'supervisor_db_id' => (int) $id,
                'supervisor_id'    => $supervisor['supervisor_id'],
                'habilitations'    => $selected,
            ]
        );

        $this->setFlash('success', sprintf(
            'Habilitations de « %s %s » mises à jour.',
            htmlspecialchars($supervisor['first_name']),
            htmlspecialchars($supervisor['last_name'])
        ));
        $this->redirect('/moderation/supervisors');
    }

    /**
     * Formulaire de modification de l'identifiant et du PIN.
     */
    public function editCredentials(string $id): void
    {
        $this->requireModerator();

        $supervisor = $this->supervisorModel->find((int) $id);
        if (!$supervisor) {
            $this->setFlash('danger', 'Superviseur introuvable.');
            $this->redirect('/moderation/supervisors');
            return;
        }

        $this->render('moderation/supervisors/edit_credentials', [
            'title'      => 'Modifier identifiant et PIN',
            'supervisor' => $supervisor,
        ]);
    }

    /**
     * Traitement de mise à jour de l'identifiant et/ou du PIN.
     */
    public function updateCredentials(string $id): void
    {
        $this->requireModerator();
        $this->validateCSRF();

        $supervisor = $this->supervisorModel->find((int) $id);
        if (!$supervisor) {
            $this->setFlash('danger', 'Superviseur introuvable.');
            $this->redirect('/moderation/supervisors');
            return;
        }

        $newSupervisorId = trim((string) ($_POST['supervisor_id'] ?? ''));
        $newPin = trim((string) ($_POST['pin'] ?? ''));

        if ($newSupervisorId === '') {
            $this->setFlash('danger', 'L\'identifiant de supervision est obligatoire.');
            $this->redirect('/moderation/supervisors/' . (int) $id . '/edit');
            return;
        }

        if (!preg_match('/^[a-zA-Z0-9_\-]{3,64}$/', $newSupervisorId)) {
            $this->setFlash('danger', 'L\'identifiant ne doit contenir que des lettres, chiffres, tirets ou underscores (3–64 caractères).');
            $this->redirect('/moderation/supervisors/' . (int) $id . '/edit');
            return;
        }

        if ($newPin !== '' && strlen($newPin) < 4) {
            $this->setFlash('danger', 'Le code PIN doit comporter au moins 4 caractères.');
            $this->redirect('/moderation/supervisors/' . (int) $id . '/edit');
            return;
        }

        $identifierChanged = $newSupervisorId !== (string) ($supervisor['supervisor_id'] ?? '');
        $pinChanged = $newPin !== '';

        if (!$identifierChanged && !$pinChanged) {
            $this->setFlash('info', 'Aucune modification détectée.');
            $this->redirect('/moderation/supervisors');
            return;
        }

        try {
            if ($identifierChanged) {
                $this->supervisorModel->setSupervisorId((int) $id, $newSupervisorId);
            }
        } catch (\InvalidArgumentException $e) {
            $this->setFlash('danger', $e->getMessage());
            $this->redirect('/moderation/supervisors/' . (int) $id . '/edit');
            return;
        }

        if ($pinChanged) {
            $this->supervisorModel->setPin((int) $id, $newPin);
        }

        AuditLog::log(
            (int) $this->getCurrentUserId(),
            AuditLog::ACTION_SUPERVISOR_CREDENTIALS_UPDATE,
            [
                'supervisor_db_id' => (int) $id,
                'previous_supervisor_id' => (string) ($supervisor['supervisor_id'] ?? ''),
                'new_supervisor_id' => $newSupervisorId,
                'pin_changed' => $pinChanged,
            ]
        );

        $this->setFlash('success', 'Identifiant de supervision et paramètres PIN mis à jour.');
        $this->redirect('/moderation/supervisors');
    }

    /** Traitement création. */
    public function store(): void
    {
        $this->requireModerator();
        $this->validateCSRF();

        $firstName    = trim($_POST['first_name']    ?? '');
        $lastName     = trim($_POST['last_name']     ?? '');
        $supervisorId = trim($_POST['supervisor_id'] ?? '');
        $pin          = trim($_POST['pin']           ?? '');

        if ($firstName === '' || $lastName === '' || $supervisorId === '') {
            $this->setFlash('danger', 'Prénom, nom et identifiant sont obligatoires.');
            $this->redirect('/moderation/supervisors/create');
            return;
        }

        if (!preg_match('/^[a-zA-Z0-9_\-]{3,64}$/', $supervisorId)) {
            $this->setFlash('danger', 'L\'identifiant ne doit contenir que des lettres, chiffres, tirets ou underscores (3–64 caractères).');
            $this->redirect('/moderation/supervisors/create');
            return;
        }

        // Si le PIN n'est pas fourni, en générer un automatiquement
        $generatedPin = null;
        if ($pin === '') {
            $pin          = Supervisor::generatePin();
            $generatedPin = $pin;
        } elseif (strlen($pin) < 4) {
            $this->setFlash('danger', 'Le code PIN doit comporter au moins 4 caractères.');
            $this->redirect('/moderation/supervisors/create');
            return;
        }

        try {
            $newId = $this->supervisorModel->createSupervisor(
                $firstName,
                $lastName,
                $supervisorId,
                $pin,
                (int) $this->getCurrentUserId()
            );

            foreach ($this->getAssignableHabilitations() as $featureKey => $_label) {
                if (in_array($featureKey, (array) ($_POST['habilitations'] ?? []), true)) {
                    $this->habilitationModel->grantAuthorization($newId, $featureKey);
                }
            }
        } catch (\InvalidArgumentException $e) {
            $this->setFlash('danger', $e->getMessage());
            $this->redirect('/moderation/supervisors/create');
            return;
        }

        AuditLog::log(
            (int) $this->getCurrentUserId(),
            AuditLog::ACTION_SUPERVISOR_CREATE,
            ['supervisor_id' => $supervisorId, 'name' => $firstName . ' ' . $lastName]
        );

        if ($generatedPin !== null) {
            $this->setFlash('success', sprintf(
                'Superviseur « %s %s » créé. PIN généré : <strong>%s</strong> — notez-le, il ne sera plus affiché.',
                htmlspecialchars($firstName),
                htmlspecialchars($lastName),
                $generatedPin
            ));
        } else {
            $this->setFlash('success', sprintf('Superviseur « %s %s » créé.', $firstName, $lastName));
        }

        $this->redirect('/moderation/supervisors');
    }

    /** Bascule statut actif/désactivé. */
    public function toggleStatus(string $id): void
    {
        $this->requireModerator();
        $this->validateCSRF();

        $supervisor = $this->supervisorModel->find((int) $id);
        if (!$supervisor) {
            $this->setFlash('danger', 'Superviseur introuvable.');
            $this->redirect('/moderation/supervisors');
            return;
        }

        $newStatus = $supervisor['status'] === 'active' ? 'disabled' : 'active';
        $this->supervisorModel->setStatus((int) $id, $newStatus);

        AuditLog::log(
            (int) $this->getCurrentUserId(),
            AuditLog::ACTION_SUPERVISOR_TOGGLE,
            [
                'supervisor_db_id' => (int) $id,
                'supervisor_id'    => $supervisor['supervisor_id'],
                'new_status'       => $newStatus,
            ]
        );

        $this->setFlash(
            'success',
            sprintf(
                'Superviseur « %s %s » %s.',
                $supervisor['first_name'],
                $supervisor['last_name'],
                $newStatus === 'active' ? 'réactivé' : 'désactivé'
            )
        );
        $this->redirect('/moderation/supervisors');
    }

    /** Réinitialise le PIN et affiche le nouveau PIN en flash. */
    public function resetPin(string $id): void
    {
        $this->requireModerator();
        $this->validateCSRF();

        $supervisor = $this->supervisorModel->find((int) $id);
        if (!$supervisor) {
            $this->setFlash('danger', 'Superviseur introuvable.');
            $this->redirect('/moderation/supervisors');
            return;
        }

        $newPin = $this->supervisorModel->resetPin((int) $id);

        AuditLog::log(
            (int) $this->getCurrentUserId(),
            AuditLog::ACTION_SUPERVISOR_PIN_RESET,
            [
                'supervisor_db_id' => (int) $id,
                'supervisor_id'    => $supervisor['supervisor_id'],
            ]
        );

        $this->setFlash('success', sprintf(
            'PIN de « %s %s » réinitialisé : <strong>%s</strong> — notez-le, il ne sera plus affiché.',
            htmlspecialchars($supervisor['first_name']),
            htmlspecialchars($supervisor['last_name']),
            $newPin
        ));
        $this->redirect('/moderation/supervisors');
    }

    // ─────────────────────────────────────────────────────────────────────────
    // BYPASS — Authentification superviseur pour contourner un feature flag
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Affiche le formulaire d'authentification de bypass.
     * GET /supervisor/bypass?feature=<key>&redirect=<url>
     */
    public function bypassForm(): void
    {
        $featureKey  = trim($_GET['feature']  ?? '');
        $redirectUrl = trim($_GET['redirect'] ?? '/');
        $isStepUp    = $featureKey === self::ACCOUNT_CONTROL_STEPUP_KEY;
        $flag        = FeatureFlag::get($featureKey);
        $featureLabel = $this->getBypassFeatureLabel($featureKey, $isStepUp, $flag);

        if ($isStepUp && !$this->canUseStepUpFlow()) {
            $this->setFlash('warning', 'Votre session modérateur n\'est plus active. Veuillez vous reconnecter.');
            $this->redirect('/login');
            return;
        }

        if ($featureKey === '') {
            $this->redirect('/');
            return;
        }

        // Si la fonctionnalité est en fait activée, rediriger directement
        if (!$isStepUp && $flag !== null && FeatureFlag::isEnabled($featureKey)) {
            $this->redirect($this->safeRedirect($redirectUrl));
            return;
        }

        // Si un bypass est déjà actif en session, rediriger directement
        if (Supervisor::hasBypass($featureKey)) {
            $this->redirect($this->safeRedirect($redirectUrl));
            return;
        }

        $this->render('supervisor/bypass_form', [
            'title'       => 'Authentification superviseur',
            'featureKey'  => $featureKey,
            'featureLabel'=> $featureLabel,
            'redirectUrl' => $redirectUrl,
            'isStepUp'    => $isStepUp,
            'error'       => null,
        ]);
    }

    /**
     * Traitement de l'authentification de bypass.
     * POST /supervisor/bypass
     */
    public function bypassAuthenticate(): void
    {
        $this->validateCSRF();

        $featureKey   = trim($_POST['feature']       ?? '');
        $supervisorId = trim($_POST['supervisor_id'] ?? '');
        $pin          = trim($_POST['pin']           ?? '');
        $redirectUrl  = trim($_POST['redirect']      ?? '/');
        $isStepUp     = $featureKey === self::ACCOUNT_CONTROL_STEPUP_KEY;

        if ($isStepUp && !$this->canUseStepUpFlow()) {
            $this->setFlash('warning', 'Votre session modérateur n\'est plus active. Veuillez vous reconnecter.');
            $this->redirect('/login');
            return;
        }

        $flag = FeatureFlag::get($featureKey);
        $featureLabel = $this->getBypassFeatureLabel($featureKey, $isStepUp, $flag);

        $renderError = function (string $msg) use ($featureKey, $featureLabel, $redirectUrl, $isStepUp): void {
            http_response_code(401);
            $this->render('supervisor/bypass_form', [
                'title'        => 'Authentification superviseur',
                'featureKey'   => $featureKey,
                'featureLabel' => $featureLabel,
                'redirectUrl'  => $redirectUrl,
                'isStepUp'     => $isStepUp,
                'error'        => $msg,
            ]);
            exit;
        };

        if ($featureKey === '' || $supervisorId === '' || $pin === '') {
            $renderError('Tous les champs sont obligatoires.');
        }

        $supervisor = $this->supervisorModel->authenticate($supervisorId, $pin);

        if ($supervisor === null) {
            AuditLog::log(
                null,
                AuditLog::ACTION_SUPERVISOR_BYPASS_FAIL,
                [
                    'feature_key'        => $featureKey,
                    'attempted_id'       => $supervisorId,
                    'ip'                 => $_SERVER['REMOTE_ADDR'] ?? '',
                ]
            );
            $renderError('Identifiant ou code PIN incorrect.');
        }

        if (!$this->habilitationModel->hasAuthorization((int) $supervisor['id'], $featureKey)) {
            AuditLog::log(
                null,
                AuditLog::ACTION_SUPERVISOR_BYPASS_FAIL,
                [
                    'feature_key'        => $featureKey,
                    'attempted_id'       => $supervisorId,
                    'supervisor_db_id'    => (int) $supervisor['id'],
                    'reason'             => 'unauthorized_feature',
                    'ip'                 => $_SERVER['REMOTE_ADDR'] ?? '',
                ]
            );
            $renderError('Echec authentification superviseur: fonctionnalité non autorisée');
        }

        // Bypass accordé
        Supervisor::grantBypass($featureKey, (int) $supervisor['id']);

        AuditLog::log(
            null,
            AuditLog::ACTION_SUPERVISOR_BYPASS,
            [
                'feature_key'      => $featureKey,
                'supervisor_db_id' => (int) $supervisor['id'],
                'supervisor_id'    => $supervisor['supervisor_id'],
                'name'             => $supervisor['first_name'] . ' ' . $supervisor['last_name'],
                'ip'               => $_SERVER['REMOTE_ADDR'] ?? '',
            ]
        );

        $this->redirect($this->safeRedirect($redirectUrl));
    }

    /**
     * Rejeu automatique d'un formulaire POST après authentification superviseur.
     * GET /supervisor/bypass/replay?pending=<token>
     *
     * Charge les données POST sauvegardées en session, les injecte dans un
     * formulaire caché et le soumet automatiquement via JavaScript.
     * La session est nettoyée dès la lecture pour éviter tout rejeu non voulu.
     */
    public function bypassReplay(): void
    {
        $token = trim($_GET['pending'] ?? '');

        if (!preg_match('/^[0-9a-f]{32}$/', $token)) {
            $this->setFlash('warning', 'Lien de reprise invalide.');
            $this->redirect('/');
            return;
        }

        $sessionKey = 'bypass_pending_' . $token;
        $pending    = \App\Core\Session::get($sessionKey);

        if (!$pending || time() > ($pending['expires_at'] ?? 0)) {
            \App\Core\Session::remove($sessionKey);
            $this->setFlash('warning', 'Le lien de reprise a expiré (15 min). Veuillez recommencer.');
            $this->redirect($this->resolveReplayFallbackUrl($pending));
            return;
        }

        if (($pending['feature'] ?? '') === self::ACCOUNT_CONTROL_STEPUP_KEY && !$this->canUseStepUpFlow()) {
            \App\Core\Session::remove($sessionKey);
            $this->setFlash('warning', 'Votre session modérateur n\'est plus active. Veuillez vous reconnecter.');
            $this->redirect('/login');
            return;
        }

        if (!Supervisor::hasBypass($pending['feature'])) {
            // Le bypass n'est plus actif (ne devrait pas arriver en pratique)
            \App\Core\Session::remove($sessionKey);
            $this->setFlash('warning', 'Le bypass superviseur n\'est plus actif.');
            $this->redirect('/');
            return;
        }

        // Lecture unique : on supprime immédiatement les données de la session
        \App\Core\Session::remove($sessionKey);

        $this->render('supervisor/bypass_replay', [
            'title'  => 'Reprise en cours…',
            'action' => $pending['action'],
            'fields' => $pending['data'],
        ], '');
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Helpers
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Valide l'URL de redirection : doit être relative (commence par /).
     * Prévient les open-redirect.
     */
    private function safeRedirect(string $url): string
    {
        if ($url === '' || $url[0] !== '/') {
            return '/';
        }
        // Rejeter les doubles slashes du type //evil.com
        if (str_starts_with($url, '//')) {
            return '/';
        }
        return $url;
    }

    /**
     * Step-up autorisé uniquement pour un modérateur connecté
     * avec une prise de main active.
     */
    private function canUseStepUpFlow(): bool
    {
        $authId = $this->getAuthenticatedUserId();
        if ($authId === null) {
            return false;
        }

        if (!$this->isModerator()) {
            return false;
        }

        return $this->isAccountControlActive();
    }

    /**
     * Évite les redirections vers une route POST lors d'un replay expiré.
     */
    private function resolveReplayFallbackUrl(mixed $pending): string
    {
        if ($this->getAuthenticatedUserId() === null) {
            return '/login';
        }

        if ($this->isModerator()) {
            return '/moderation/users';
        }

        return '/dashboard';
    }

    private function getBypassFeatureLabel(string $featureKey, bool $isStepUp, ?array $flag = null): string
    {
        if ($isStepUp) {
            return 'Validation opération de modération';
        }

        $fallbackLabels = [
            'accounts.event_open' => 'Ouverture de compte événementiel',
        ];

        if ($featureKey === self::EVENT_TYCOON_DEV_MODE_KEY) {
            return 'Mode développeur du tycoon';
        }

        return $flag['label'] ?? ($fallbackLabels[$featureKey] ?? $featureKey);
    }

    /**
     * Habilitations proposées aux superviseurs.
     *
     * Inclut toutes les fonctionnalités présentes sur /moderation/features
     * (feature_flags), ainsi que les clés de bypass internes non pilotées
     * par feature flag.
     */
    private function getAssignableHabilitations(): array
    {
        $habilitations = [
            self::ACCOUNT_CONTROL_STEPUP_KEY => 'Validation des opérations de modération',
            self::EVENT_TYCOON_DEV_MODE_KEY  => 'Mode développeur du tycoon événementiel',
            'accounts.event_open'            => 'Ouverture de compte événementiel',
        ];

        foreach (FeatureFlag::getAllGrouped() as $flags) {
            foreach ($flags as $flag) {
                $key = (string) ($flag['flag_key'] ?? '');
                if ($key === '') {
                    continue;
                }
                $label = trim((string) ($flag['label'] ?? ''));
                $habilitations[$key] = $label !== '' ? $label : $key;
            }
        }

        asort($habilitations, SORT_NATURAL | SORT_FLAG_CASE);

        return $habilitations;
    }
}
