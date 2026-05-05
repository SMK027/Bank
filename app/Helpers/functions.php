<?php

declare(strict_types=1);

use App\Core\CSRF;
use App\Core\Session;

/**
 * Fonctions d'aide globales.
 */

/**
 * Échappe une chaîne pour la sortie HTML (protection XSS).
 */
function e(?string $value): string
{
    if ($value === null) {
        return '';
    }
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

/**
 * Retourne l'adresse IP réelle du client.
 * Gère les cas derrière un proxy / reverse proxy / Docker.
 */
function get_client_ip(): string
{
    if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $ips = array_map('trim', explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']));
        $clientIp = $ips[0];
        if (filter_var($clientIp, FILTER_VALIDATE_IP)) {
            return $clientIp;
        }
    }

    if (!empty($_SERVER['HTTP_X_REAL_IP']) && filter_var($_SERVER['HTTP_X_REAL_IP'], FILTER_VALIDATE_IP)) {
        return $_SERVER['HTTP_X_REAL_IP'];
    }

    $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';

    // En développement Docker : la gateway (172.x.x.1) représente le host local
    if (preg_match('/^172\.(1[6-9]|2[0-9]|3[01])\.\d+\.1$/', $ip)) {
        return '127.0.0.1';
    }

    return $ip;
}

/**
 * Génère un champ CSRF pour les formulaires.
 */
function csrf_field(): string
{
    return CSRF::field();
}

/**
 * Génère le token CSRF courant.
 */
function csrf_token(): string
{
    return CSRF::generate();
}

/**
 * Récupère l'URL complète de l'application.
 */
function url(string $path = ''): string
{
    $base = rtrim(getenv('APP_URL') ?: 'http://localhost:8080', '/');
    return $base . '/' . ltrim($path, '/');
}

/**
 * Vérifie si l'utilisateur est connecté.
 */
function is_authenticated(): bool
{
    return Session::get('user_id') !== null;
}

/**
 * Retourne l'ID de l'utilisateur connecté.
 */
function current_user_id(): ?int
{
    $id = Session::get('user_id');
    return $id ? (int) $id : null;
}

/**
 * Retourne le nom d'utilisateur connecté.
 */
function current_username(): string
{
    return Session::get('username') ?? '';
}

/**
 * Vérifie si l'utilisateur connecté est modérateur.
 */
function is_moderator(): bool
{
    return Session::get('global_role') === 'moderator';
}

/**
 * Retourne l'URL de l'avatar de l'utilisateur connecté.
 */
function current_avatar(): string
{
    return Session::get('avatar') ?? '';
}

/**
 * Retourne le rôle global de l'utilisateur.
 */
function current_global_role(): string
{
    return Session::get('global_role') ?? 'user';
}

/**
 * Formate un montant avec notation compacte pour les grandes valeurs.
 *
 * - En dessous de 1 000 000 : formatage standard (1 234,56)
 * - Au-delà : notation compacte avec suffixe (M / Md / Bn / Bd …)
 *   La valeur exacte est toujours accessible via l'attribut title du <abbr>.
 *
 * @param float  $amount   Montant brut
 * @param int    $decimals Décimales pour la partie compacte (défaut : 2)
 * @return string          Chaîne HTML (avec <abbr> si compaction active)
 */
function fmt_amount_smart(float $amount, int $decimals = 2): string
{
    $exact = number_format($amount, $decimals, ',', ' ');
    $abs   = abs($amount);

    if ($abs >= 1_000_000_000_000_000_000) {
        $compact = number_format($amount / 1_000_000_000_000_000_000, $decimals, ',', ' ') . '&nbsp;Tn';
    } elseif ($abs >= 1_000_000_000_000_000) {
        $compact = number_format($amount / 1_000_000_000_000_000, $decimals, ',', ' ') . '&nbsp;Bd';
    } elseif ($abs >= 1_000_000_000_000) {
        $compact = number_format($amount / 1_000_000_000_000, $decimals, ',', ' ') . '&nbsp;Bn';
    } elseif ($abs >= 1_000_000_000) {
        $compact = number_format($amount / 1_000_000_000, $decimals, ',', ' ') . '&nbsp;Md';
    } elseif ($abs >= 1_000_000) {
        $compact = number_format($amount / 1_000_000, $decimals, ',', ' ') . '&nbsp;M';
    } else {
        return $exact;
    }

    return '<abbr title="' . htmlspecialchars($exact, ENT_QUOTES, 'UTF-8') . '" style="text-decoration:underline dotted;cursor:help;">'
        . $compact
        . '</abbr>';
}

/**
 * Formate une date pour l'affichage.
 */
function format_date(?string $date, string $format = 'd/m/Y H:i'): string
{
    if (!$date) {
        return '-';
    }
    $dt = new DateTime($date);
    return $dt->format($format);
}

/**
 * Parse une saisie de date flexible (formats multiples).
 * Accepte : d/m/Y H:i, d/m/Y, Y-m-d\TH:i, Y-m-d H:i:s, Y-m-d H:i
 * Retourne un DateTime ou null si le parsing échoue.
 */
function parse_datetime_input(?string $raw): ?DateTime
{
    $raw = trim($raw ?? '');
    if ($raw === '') {
        return null;
    }

    $formats = [
        'd/m/Y H:i',
        'd/m/Y',
        'Y-m-d\TH:i',
        'Y-m-d H:i:s',
        'Y-m-d H:i',
    ];

    foreach ($formats as $fmt) {
        $dt = DateTime::createFromFormat($fmt, $raw);
        if ($dt !== false) {
            return $dt;
        }
    }

    return null;
}

/**
 * Formate une date relative (il y a X minutes, etc.).
 */
function time_ago(?string $date): string
{
    if (!$date) {
        return '-';
    }

    $now = new DateTime();
    $then = new DateTime($date);
    $diff = $now->diff($then);

    if ($diff->y > 0) {
        return "il y a {$diff->y} an" . ($diff->y > 1 ? 's' : '');
    }
    if ($diff->m > 0) {
        return "il y a {$diff->m} mois";
    }
    if ($diff->d > 0) {
        return "il y a {$diff->d} jour" . ($diff->d > 1 ? 's' : '');
    }
    if ($diff->h > 0) {
        return "il y a {$diff->h} heure" . ($diff->h > 1 ? 's' : '');
    }
    if ($diff->i > 0) {
        return "il y a {$diff->i} minute" . ($diff->i > 1 ? 's' : '');
    }
    return "à l'instant";
}
