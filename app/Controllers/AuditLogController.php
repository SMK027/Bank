<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Models\AuditLog;

class AuditLogController extends Controller
{
    private AuditLog $auditModel;

    private const PER_PAGE = 50;

    public function __construct()
    {
        $this->auditModel = new AuditLog();
    }

    /**
     * Liste paginée du journal d'audit — modérateurs uniquement.
     */
    public function index(): void
    {
        $this->requireModerator();

        // ── Filtres ──────────────────────────────────────────────────────────
        $filters = [
            'action'            => trim($_GET['action']            ?? ''),
            'username'          => trim($_GET['username']          ?? ''),
            'date_from'         => trim($_GET['date_from']         ?? ''),
            'date_to'           => trim($_GET['date_to']           ?? ''),
            'target_account_id' => trim($_GET['target_account_id'] ?? ''),
        ];
        // Supprimer les filtres vides
        $filters = array_filter($filters, fn($v) => $v !== '');

        // Valider les dates
        foreach (['date_from', 'date_to'] as $key) {
            if (isset($filters[$key]) && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $filters[$key])) {
                unset($filters[$key]);
            }
        }

        // ── Pagination ───────────────────────────────────────────────────────
        $page   = max(1, (int) ($_GET['page'] ?? 1));
        $total  = $this->auditModel->countFiltered($filters);
        $pages  = max(1, (int) ceil($total / self::PER_PAGE));
        $page   = min($page, $pages);
        $offset = ($page - 1) * self::PER_PAGE;

        $entries = $this->auditModel->getFiltered($filters, self::PER_PAGE, $offset);

        // Décoder les détails JSON pour l'affichage
        foreach ($entries as &$e) {
            $e['details_decoded'] = $e['details'] ? json_decode($e['details'], true) : [];
        }
        unset($e);

        $this->render('moderation/audit_log', [
            'title'    => 'Journal d\'audit',
            'entries'  => $entries,
            'filters'  => $filters,
            'page'     => $page,
            'pages'    => $pages,
            'total'    => $total,
            'perPage'  => self::PER_PAGE,
            'actions'  => AuditLog::LABELS,
        ]);
    }
}
