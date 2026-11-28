<?php
declare(strict_types=1);

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/supabase.php';

if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'httponly' => true,
        'samesite' => 'Lax',
        'secure' => !empty($_SERVER['HTTPS']),
    ]);
    session_start();
}

define('SESSION_TIMEOUT_SECONDS', 1800);

const ROLE_SUPER_ADMIN = 'Super Admin';
const ROLE_OVPRE_ADMIN = 'OVPRE Admin';
const ROLE_RESEARCH_COORDINATOR = 'Research Coordinator';
const ROLE_EXTENSION_COORDINATOR = 'Extension Coordinator';
const ROLE_FACULTY_EXTENSIONIST = 'Faculty Extensionist';
const ROLE_RESEARCHER = 'Researcher';
const ROLE_RESEARCH_DIRECTOR = 'Research Director';
const ROLE_EXTENSION_DIRECTOR = 'Extension Director';
const ROLE_RESEARCH_CREATIVE_WORKS_DIRECTOR = 'Director for Research and Creative Works';
const ROLE_OVPRE_SECRETARIAT = 'OVPRE Secretariat';
const SELF_REGISTERABLE_ROLES = [ROLE_RESEARCH_COORDINATOR, ROLE_EXTENSION_COORDINATOR, ROLE_FACULTY_EXTENSIONIST, ROLE_RESEARCHER];

function role_access_group(string $role): string {
    return match ($role) {
        ROLE_RESEARCH_DIRECTOR, ROLE_RESEARCH_CREATIVE_WORKS_DIRECTOR => ROLE_RESEARCH_DIRECTOR,
        ROLE_EXTENSION_DIRECTOR => ROLE_EXTENSION_DIRECTOR,
        ROLE_OVPRE_SECRETARIAT => ROLE_OVPRE_ADMIN,
        default => $role,
    };
}

function announcement_audience_roles(string $audience): ?array {
    return match ($audience) {
        'All' => [],
        'All Faculty' => [ROLE_RESEARCHER, ROLE_FACULTY_EXTENSIONIST],
        'Research Director' => [ROLE_RESEARCH_DIRECTOR, ROLE_RESEARCH_CREATIVE_WORKS_DIRECTOR],
        'Extension Director' => [ROLE_EXTENSION_DIRECTOR],
        'Directors' => [ROLE_RESEARCH_DIRECTOR, ROLE_RESEARCH_CREATIVE_WORKS_DIRECTOR, ROLE_EXTENSION_DIRECTOR],
        'Research Coordinators' => [ROLE_RESEARCH_COORDINATOR],
        'Extension Coordinators' => [ROLE_EXTENSION_COORDINATOR],
        'Coordinators' => [ROLE_RESEARCH_COORDINATOR, ROLE_EXTENSION_COORDINATOR],
        'Faculty Extensionists' => [ROLE_FACULTY_EXTENSIONIST],
        'OVPRE Staff' => [ROLE_OVPRE_ADMIN, ROLE_OVPRE_SECRETARIAT],
        'Faculty Researchers', 'Other Authorized Users', 'Researchers' => [ROLE_RESEARCHER],
        default => null,
    };
}

function announcement_audience_for_role(string $role): ?string {
    return match ($role) {
        ROLE_RESEARCH_DIRECTOR, ROLE_RESEARCH_CREATIVE_WORKS_DIRECTOR => 'Research Director',
        ROLE_EXTENSION_DIRECTOR => 'Extension Director',
        ROLE_RESEARCH_COORDINATOR => 'Research Coordinators',
        ROLE_EXTENSION_COORDINATOR => 'Extension Coordinators',
        ROLE_FACULTY_EXTENSIONIST => 'Faculty Extensionists',
        ROLE_OVPRE_ADMIN, ROLE_OVPRE_SECRETARIAT => 'OVPRE Staff',
        ROLE_RESEARCHER => 'Faculty Researchers',
        default => null,
    };
}

function announcement_viewable_audiences(string $role): array {
    $validEnumValues = [
        'All', 'All Faculty', 'Research Director', 'Extension Director', 'Directors',
        'Research Coordinators', 'Extension Coordinators', 'Coordinators',
        'Faculty Extensionists', 'OVPRE Staff', 'Faculty Researchers',
        'Other Authorized Users', 'Faculty', 'Researchers', 'Staff'
    ];
    if (in_array($role, [ROLE_SUPER_ADMIN, ROLE_OVPRE_ADMIN], true)) {
        return $validEnumValues;
    }
    $audiences = ['All'];
    $audience = announcement_audience_for_role($role);
    if ($audience !== null) {
        $audiences[] = $audience;
        if ($audience === 'Faculty Researchers') {
            $audiences[] = 'Other Authorized Users';
            $audiences[] = 'Researchers';
        }
    }
    $legacyAudiences = match ($role) {
        ROLE_RESEARCH_DIRECTOR, ROLE_RESEARCH_CREATIVE_WORKS_DIRECTOR, ROLE_RESEARCH_COORDINATOR, ROLE_RESEARCHER => ['Researchers'],
        ROLE_EXTENSION_DIRECTOR, ROLE_EXTENSION_COORDINATOR => ['Staff'],
        ROLE_FACULTY_EXTENSIONIST => ['Faculty', 'Staff'],
        ROLE_OVPRE_ADMIN, ROLE_OVPRE_SECRETARIAT => ['Staff'],
        default => [],
    };
    $combined = array_values(array_unique(array_merge($audiences, $legacyAudiences)));
    return array_values(array_intersect($validEnumValues, $combined));
}

function role_dashboard_path(string $role): ?string {
    $role = role_access_group($role);
    return match ($role) {
        ROLE_SUPER_ADMIN, ROLE_OVPRE_ADMIN,
        ROLE_RESEARCH_DIRECTOR, ROLE_EXTENSION_DIRECTOR,
        ROLE_RESEARCH_COORDINATOR, ROLE_EXTENSION_COORDINATOR,
        ROLE_FACULTY_EXTENSIONIST, ROLE_RESEARCHER => match ($role) {
            ROLE_RESEARCH_DIRECTOR => 'dashboard.php',
            ROLE_EXTENSION_DIRECTOR => 'dashboard.php',
            ROLE_RESEARCH_COORDINATOR => 'research-coordinator/dashboard.php',
            ROLE_EXTENSION_COORDINATOR => 'extension-coordinator/dashboard.php',
            ROLE_FACULTY_EXTENSIONIST => 'faculty-extensionist/dashboard.php',
            ROLE_RESEARCHER => 'researcher/dashboard.php',
            default => 'dashboard.php',
        },
        default => null,
    };
}

function parse_assigned_roles(mixed $raw): array {
    if (empty($raw)) {
        return [];
    }
    if (is_array($raw)) {
        return array_values(array_unique(array_filter(array_map('trim', $raw))));
    }
    if (is_string($raw)) {
        $raw = trim($raw);
        if ($raw === '') {
            return [];
        }
        if (str_starts_with($raw, '[') && str_ends_with($raw, ']')) {
            $decoded = json_decode($raw, true);
            if (is_array($decoded)) {
                return array_values(array_unique(array_filter(array_map('trim', $decoded))));
            }
        }
        return array_values(array_unique(array_filter(array_map('trim', explode(',', $raw)))));
    }
    return [];
}

function find_designated_user_for_role(string $role): ?array {
    static $cache = [];
    if (isset($cache[$role])) {
        return $cache[$role];
    }

    $preferredUsernames = match ($role) {
        ROLE_SUPER_ADMIN => ['admin', 'zyrus'],
        ROLE_RESEARCH_DIRECTOR => ['jcigaral2', 'jeysii'],
        ROLE_EXTENSION_DIRECTOR => ['edrianbanaagt', 'edrian'],
        ROLE_RESEARCH_COORDINATOR => ['mdelosreyes', 'kendrix', 'owbiwan2'],
        ROLE_EXTENSION_COORDINATOR => ['vramos'],
        ROLE_FACULTY_EXTENSIONIST => ['lbautista', 'rcruz', 'jaquino'],
        ROLE_RESEARCHER => ['athorne', 'kvaldez', 'gmendoza', 'marielramos12', 'johncarl28'],
        default => [],
    };

    $user = null;
    if (!empty($preferredUsernames)) {
        $placeholders = implode(',', array_fill(0, count($preferredUsernames), '?'));
        $stmt = db()->prepare("SELECT * FROM users WHERE status = 'Active' AND deleted_at IS NULL AND username IN ({$placeholders}) ORDER BY id ASC LIMIT 1");
        $stmt->execute($preferredUsernames);
        $user = $stmt->fetch() ?: null;
    }

    if (!$user) {
        $targetGroup = role_access_group($role);
        $stmt = db()->prepare("SELECT * FROM users WHERE (role = ? OR role = ?) AND status = 'Active' AND deleted_at IS NULL ORDER BY id ASC LIMIT 1");
        $stmt->execute([$role, $targetGroup]);
        $user = $stmt->fetch() ?: null;
    }

    if ($user) {
        $cache[$role] = $user;
    }
    return $user;
}

function user_available_roles(?array $user = null): array {
    if ($user === null) {
        $user = current_user();
    }
    if (!$user) {
        return [];
    }

    $primaryRole = (string) ($user['primary_role'] ?? $user['role'] ?? '');
    $assignedRolesRaw = $user['assigned_roles'] ?? '';
    $additionalRoles = parse_assigned_roles($assignedRolesRaw);

    // If session was switched from an original user or user is an admin: provide all designated portal roles
    $isSwitchedSession = !empty($_SESSION['original_user_id']);
    if ($isSwitchedSession || in_array($primaryRole, [ROLE_SUPER_ADMIN, ROLE_OVPRE_ADMIN], true)) {
        $allPortalRoles = [
            ROLE_SUPER_ADMIN,
            ROLE_RESEARCH_DIRECTOR,
            ROLE_EXTENSION_DIRECTOR,
            ROLE_RESEARCH_COORDINATOR,
            ROLE_EXTENSION_COORDINATOR,
            ROLE_FACULTY_EXTENSIONIST,
            ROLE_RESEARCHER,
        ];
        if (!empty($additionalRoles)) {
            $allPortalRoles = array_merge($allPortalRoles, $additionalRoles);
        }
        return array_values(array_unique(array_filter($allPortalRoles)));
    }

    $roles = array_merge([$primaryRole], $additionalRoles);
    return array_values(array_unique(array_filter($roles)));
}

function role_display_info(string $role): array {
    return match ($role) {
        ROLE_SUPER_ADMIN => [
            'label' => 'Super Admin',
            'portal' => 'System Administration',
            'icon' => 'ri-shield-keyhole-line',
            'path' => 'dashboard.php',
            'badge_color' => '#fef2f2',
            'badge_text' => '#b91c1c',
        ],
        ROLE_OVPRE_ADMIN => [
            'label' => 'OVPRE Admin',
            'portal' => 'Admin Operations',
            'icon' => 'ri-admin-line',
            'path' => 'dashboard.php',
            'badge_color' => '#eff6ff',
            'badge_text' => '#1d4ed8',
        ],
        ROLE_RESEARCH_DIRECTOR => [
            'label' => 'Research Director',
            'portal' => 'Research Director Portal',
            'icon' => 'ri-compass-3-line',
            'path' => 'dashboard.php',
            'badge_color' => '#f0fdf4',
            'badge_text' => '#15803d',
        ],
        ROLE_RESEARCH_CREATIVE_WORKS_DIRECTOR => [
            'label' => 'Research & Creative Works Dir.',
            'portal' => 'Research Director Portal',
            'icon' => 'ri-compass-3-line',
            'path' => 'dashboard.php',
            'badge_color' => '#f0fdf4',
            'badge_text' => '#15803d',
        ],
        ROLE_EXTENSION_DIRECTOR => [
            'label' => 'Extension Director',
            'portal' => 'Extension Director Portal',
            'icon' => 'ri-community-line',
            'path' => 'dashboard.php',
            'badge_color' => '#fff7ed',
            'badge_text' => '#c2410c',
        ],
        ROLE_RESEARCH_COORDINATOR => [
            'label' => 'Research Coordinator',
            'portal' => 'Research Coord. Portal',
            'icon' => 'ri-flask-line',
            'path' => 'research-coordinator/dashboard.php',
            'badge_color' => '#f0fdf4',
            'badge_text' => '#166534',
        ],
        ROLE_EXTENSION_COORDINATOR => [
            'label' => 'Extension Coordinator',
            'portal' => 'Extension Coord. Portal',
            'icon' => 'ri-shake-hands-line',
            'path' => 'extension-coordinator/dashboard.php',
            'badge_color' => '#fff7ed',
            'badge_text' => '#c2410c',
        ],
        ROLE_FACULTY_EXTENSIONIST => [
            'label' => 'Faculty Extensionist',
            'portal' => 'Faculty Extensionist Portal',
            'icon' => 'ri-team-line',
            'path' => 'faculty-extensionist/dashboard.php',
            'badge_color' => '#fefce8',
            'badge_text' => '#a16207',
        ],
        ROLE_RESEARCHER => [
            'label' => 'Faculty Researcher',
            'portal' => 'Researcher Portal',
            'icon' => 'ri-microscope-line',
            'path' => 'researcher/dashboard.php',
            'badge_color' => '#f0fdf4',
            'badge_text' => '#15803d',
        ],
        ROLE_OVPRE_SECRETARIAT => [
            'label' => 'OVPRE Secretariat',
            'portal' => 'Secretariat Portal',
            'icon' => 'ri-file-user-line',
            'path' => 'dashboard.php',
            'badge_color' => '#eff6ff',
            'badge_text' => '#1d4ed8',
        ],
        default => [
            'label' => $role,
            'portal' => $role . ' Portal',
            'icon' => 'ri-user-line',
            'path' => role_dashboard_path($role) ?? 'dashboard.php',
            'badge_color' => '#f1f5f9',
            'badge_text' => '#475569',
        ],
    };
}

function render_profile_role_switcher(array $user, string $pathPrefix = ''): string {
    $availableRoles = user_available_roles($user);
    if (count($availableRoles) <= 1) {
        return '';
    }

    $activeRole = (string) ($user['active_role'] ?? $user['role'] ?? '');
    $currentUserId = (int) ($user['id'] ?? 0);
    $isSwitched = !empty($_SESSION['original_user_id']) && ((int) $_SESSION['original_user_id'] !== $currentUserId);
    $token = csrf_token();

    $html = '<div class="profile-role-switcher" style="padding: 10px; margin: 8px 0; background: #ffffff; border: 1px solid #e2e8f0; border-radius: 10px; box-shadow: 0 1px 3px rgba(0,0,0,0.04); text-align: left;">';
    
    $html .= '<div style="padding: 0 4px 6px; font-size: 0.72rem; font-weight: 800; text-transform: uppercase; letter-spacing: 0.5px; color: #15803d; display: flex; align-items: center; justify-content: space-between; border-bottom: 1px solid #f1f5f9; margin-bottom: 6px;">';
    $html .= '<span style="display: flex; align-items: center; gap: 4px;"><i class="ri-swap-box-line" style="font-size: 0.85rem;"></i> Designated Accounts</span>';
    $html .= '<span style="font-size: 0.68rem; background: #dcfce7; color: #166534; padding: 1px 7px; border-radius: 999px; font-weight: 700;">' . count($availableRoles) . ' Portals</span>';
    $html .= '</div>';

    $html .= '<div style="display: flex; flex-direction: column; gap: 4px; max-height: 240px; overflow-y: auto;">';
    foreach ($availableRoles as $role) {
        $info = role_display_info($role);
        $designatedUser = find_designated_user_for_role($role);
        $designatedName = $designatedUser ? $designatedUser['fullname'] : '';
        $isCurrentAccount = $designatedUser ? ((int) $designatedUser['id'] === $currentUserId) : ($activeRole === $role);
        $switchUrl = h($pathPrefix . 'switch-role.php?role=' . urlencode($role) . '&csrf_token=' . $token);

        if ($isCurrentAccount) {
            $html .= '<div style="display: flex; align-items: center; justify-content: space-between; padding: 6px 10px; border-radius: 8px; background: #f0fdf4; border: 1px solid #86efac; color: #14532d; font-size: 0.82rem;">';
            $html .= '<div style="display: flex; align-items: center; gap: 8px; min-width: 0;">';
            $html .= '<i class="' . h($info['icon']) . '" style="font-size: 1rem; color: #15803d; flex-shrink: 0;"></i>';
            $html .= '<div style="display: flex; flex-direction: column; line-height: 1.25; min-width: 0;">';
            $html .= '<span style="font-weight: 800; color: #14532d; font-size: 0.82rem; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">' . h($info['label']) . '</span>';
            if ($designatedName) {
                $html .= '<span style="font-size: 0.72rem; color: #166534; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">' . h($designatedName) . '</span>';
            }
            $html .= '</div>';
            $html .= '</div>';
            $html .= '<span style="display: inline-flex; align-items: center; gap: 2px; font-size: 0.65rem; background: #15803d; color: #ffffff; padding: 2px 6px; border-radius: 999px; font-weight: 700; flex-shrink: 0;">';
            $html .= '<i class="ri-check-line"></i> Active';
            $html .= '</span>';
            $html .= '</div>';
        } else {
            $html .= '<a href="' . $switchUrl . '" class="profile-role-item" style="display: flex; align-items: center; justify-content: space-between; padding: 6px 10px; border-radius: 8px; text-decoration: none; border: 1px solid transparent; transition: all 0.15s ease;" onmouseover="this.style.background=\'#f1f5f9\'; this.style.borderColor=\'#cbd5e1\';" onmouseout="this.style.background=\'transparent\'; this.style.borderColor=\'transparent\';">';
            $html .= '<div style="display: flex; align-items: center; gap: 8px; min-width: 0;">';
            $html .= '<i class="' . h($info['icon']) . '" style="font-size: 1rem; color: #64748b; flex-shrink: 0;"></i>';
            $html .= '<div style="display: flex; flex-direction: column; line-height: 1.25; min-width: 0;">';
            $html .= '<span style="font-weight: 700; color: #1e293b; font-size: 0.82rem; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">' . h($info['label']) . '</span>';
            if ($designatedName) {
                $html .= '<span style="font-size: 0.72rem; color: #64748b; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">' . h($designatedName) . '</span>';
            }
            $html .= '</div>';
            $html .= '</div>';
            $html .= '<i class="ri-arrow-right-s-line" style="color: #94a3b8; font-size: 0.95rem; flex-shrink: 0;"></i>';
            $html .= '</a>';
        }
    }
    $html .= '</div>';

    if ($isSwitched) {
        $resetUrl = h($pathPrefix . 'switch-role.php?role=reset&csrf_token=' . $token);
        $originalUserId = (int) $_SESSION['original_user_id'];
        $origStmt = db()->prepare("SELECT fullname FROM users WHERE id = ? LIMIT 1");
        $origStmt->execute([$originalUserId]);
        $origName = (string) ($origStmt->fetchColumn() ?: 'Original Account');

        $html .= '<div style="margin-top: 8px; padding-top: 6px; border-top: 1px dashed #e2e8f0; text-align: center;">';
        $html .= '<a href="' . $resetUrl . '" style="font-size: 0.74rem; color: #15803d; background: #f0fdf4; border: 1px solid #bbf7d0; padding: 4px 10px; border-radius: 6px; text-decoration: none; display: inline-flex; align-items: center; gap: 4px; font-weight: 700;">';
        $html .= '<i class="ri-refresh-line"></i> Reset to ' . h($origName);
        $html .= '</a>';
        $html .= '</div>';
    }

    $html .= '</div>';
    return $html;
}

class SupabasePDO extends PDO {
    private ?string $lastTable = null;

    public static function normalizeSql(string $sql): string {
        // Strip MySQL backticks
        $sql = str_replace('`', '', $sql);

        // MySQL schema introspection & session settings
        $sql = preg_replace('/^\s*SHOW\s+TABLES\s*;?\s*$/i', "SELECT tablename FROM pg_tables WHERE schemaname = 'public'", $sql);
        $sql = preg_replace_callback('/^\s*SHOW\s+COLUMNS\s+FROM\s+([a-zA-Z0-9_]+)\s*;?\s*$/i', function($m) {
            return "SELECT column_name FROM information_schema.columns WHERE table_schema = 'public' AND table_name = '{$m[1]}' ORDER BY ordinal_position";
        }, $sql);
        $sql = preg_replace('/^\s*SET\s+FOREIGN_KEY_CHECKS\s*=\s*0\s*;?\s*$/i', "SET session_replication_role = 'replica'", $sql);
        $sql = preg_replace('/^\s*SET\s+FOREIGN_KEY_CHECKS\s*=\s*1\s*;?\s*$/i', "SET session_replication_role = 'origin'", $sql);
        $sql = preg_replace_callback('/ON\s+DUPLICATE\s+KEY\s+UPDATE\s+(.+)$/i', function($m) {
            $pgUpdates = preg_replace('/VALUES\(\s*([a-zA-Z0-9_]+)\s*\)/i', 'EXCLUDED.$1', $m[1]);
            return "ON CONFLICT (id) DO UPDATE SET $pgUpdates";
        }, $sql);

        // Convert double-quoted string literals outside single quotes to single-quoted literals
        $sql = preg_replace('/"([^"\r\n]+)"/', "'$1'", $sql);

        // DATE_SUB & DATE_ADD
        $sql = preg_replace('/DATE_SUB\(\s*(.+?)\s*,\s*INTERVAL\s*(\d+)\s*([a-zA-Z]+)\s*\)/i', "($1 - INTERVAL '$2 $3')", $sql);
        $sql = preg_replace('/DATE_ADD\(\s*(.+?)\s*,\s*INTERVAL\s*(\d+)\s*([a-zA-Z]+)\s*\)/i', "($1 + INTERVAL '$2 $3')", $sql);
        $sql = preg_replace('/\bINTERVAL\s+(\d+)\s+([a-zA-Z]+)\b/i', "INTERVAL '$1 $2'", $sql);

        // CURDATE() -> CURRENT_DATE
        $sql = preg_replace('/\bCURDATE\(\)/i', 'CURRENT_DATE', $sql);

        // DATE_FORMAT -> TO_CHAR
        $sql = preg_replace_callback('/DATE_FORMAT\(\s*([^,]+?)\s*,\s*[\'"]([^\'"]+)[\'"]\s*\)/i', function($m) {
            $field = trim($m[1]);
            $fmt = $m[2];
            $map = [
                '%Y' => 'YYYY',
                '%y' => 'YY',
                '%m' => 'MM',
                '%c' => 'FMMM',
                '%d' => 'DD',
                '%e' => 'FMDD',
                '%b' => 'Mon',
                '%M' => 'Month',
                '%H' => 'HH24',
                '%h' => 'HH12',
                '%i' => 'MI',
                '%s' => 'SS',
                '%p' => 'AM',
            ];
            $pgFmt = strtr($fmt, $map);
            return "TO_CHAR($field, '$pgFmt')";
        }, $sql);

        // YEAR(), MONTH(), DAY()
        $sql = preg_replace('/\bYEAR\(\s*([^)]+?)\s*\)/i', "EXTRACT(YEAR FROM $1)", $sql);
        $sql = preg_replace('/\bMONTH\(\s*([^)]+?)\s*\)/i', "EXTRACT(MONTH FROM $1)", $sql);
        $sql = preg_replace('/\bDAY\(\s*([^)]+?)\s*\)/i', "EXTRACT(DAY FROM $1)", $sql);

        // IFNULL() -> COALESCE()
        $sql = preg_replace('/\bIFNULL\(/i', 'COALESCE(', $sql);

        // Boolean aggregation in SUM()
        $sql = preg_replace("#SUM\\(([^)]+?)\\s*=\\s*(\"[^\"]+\"|'[^']+'|\\d+)\\)#i", 'COUNT(CASE WHEN $1 = $2 THEN 1 END)', $sql);
        $sql = preg_replace("#SUM\\(([^)]+?)\\s+(NOT\\s+)?IN\\s*\\(([^)]+?)\\)\\)#i", 'COUNT(CASE WHEN $1 $2IN ($3) THEN 1 END)', $sql);
        $sql = preg_replace("#SUM\\(([^)]+?\\s*(?:!=|<>)\\s*(\"[^\"]+\"|'[^']+'|\\d+))\\)#i", 'COUNT(CASE WHEN $1 THEN 1 END)', $sql);

        // Enum COALESCE type compatibility
        $sql = preg_replace('/COALESCE\(\s*([^,]+?\.status|status)\s*,\s*([^,]+?\.progress_status|progress_status)\s*\)/i', 'COALESCE(CAST($1 AS VARCHAR), CAST($2 AS VARCHAR))', $sql);

        return $sql;
    }

    public function prepare(string $query, array $options = []): PDOStatement|false {
        $normalized = self::normalizeSql($query);
        $this->detectInsertTable($normalized);
        return parent::prepare($normalized, $options);
    }

    public function query(string $query, ?int $fetchMode = null, mixed ...$fetchModeArgs): PDOStatement|false {
        $normalized = self::normalizeSql($query);
        $this->detectInsertTable($normalized);
        if ($fetchMode === null) {
            return parent::query($normalized);
        }
        return parent::query($normalized, $fetchMode, ...$fetchModeArgs);
    }

    public function exec(string $statement): int|false {
        $normalized = self::normalizeSql($statement);
        $this->detectInsertTable($normalized);
        return parent::exec($normalized);
    }

    private function detectInsertTable(string $sql): void {
        if (preg_match('/^\s*INSERT\s+INTO\s+([a-zA-Z0-9_]+)/i', $sql, $m)) {
            $this->lastTable = strtolower($m[1]);
        }
    }

    public function lastInsertId(?string $name = null): string|false {
        try {
            $id = parent::lastInsertId($name);
            if ($id !== false && $id !== '' && $id !== '0') {
                return $id;
            }
        } catch (Throwable) {
        }

        if ($name === null && $this->lastTable !== null) {
            try {
                $id = parent::lastInsertId($this->lastTable . '_id_seq');
                if ($id !== false && $id !== '' && $id !== '0') {
                    return $id;
                }
            } catch (Throwable) {
            }
        }

        try {
            $val = $this->query('SELECT LASTVAL()')->fetchColumn();
            if ($val !== false && $val !== null) {
                return (string) $val;
            }
        } catch (Throwable) {
        }

        return false;
    }
}

function db(): PDO {
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }

    if (defined('DB_DRIVER') && DB_DRIVER === 'pgsql') {
        try {
            $dsn = sprintf('pgsql:host=%s;port=%s;dbname=%s;sslmode=require', DB_HOST, DB_PORT, DB_NAME);
            $pdo = new SupabasePDO(
                $dsn,
                DB_USER,
                DB_PASS,
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_TIMEOUT => 4,
                ]
            );
            return $pdo;
        } catch (Throwable $e) {
            try {
                $pdo = new PDO(
                    'mysql:host=127.0.0.1;dbname=ovpre;charset=utf8mb4',
                    'root',
                    '',
                    [
                        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    ]
                );
                return $pdo;
            } catch (Throwable) {
                throw $e;
            }
        }
    }

    $pdo = new PDO(
        'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4',
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]
    );
    return $pdo;
}

function send_app_email(string $to, string $subject, string $body): bool {
    if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
        return false;
    }
    require_once __DIR__ . '/../vendor/phpmailer/phpmailer/src/Exception.php';
    require_once __DIR__ . '/../vendor/phpmailer/phpmailer/src/SMTP.php';
    require_once __DIR__ . '/../vendor/phpmailer/phpmailer/src/PHPMailer.php';

    try {
        $mailer = new \PHPMailer\PHPMailer\PHPMailer(true);
        $mailer->CharSet = \PHPMailer\PHPMailer\PHPMailer::CHARSET_UTF8;
        $mailer->Timeout = 15;
        $mailer->setFrom(MAIL_FROM, MAIL_FROM_NAME);
        $mailer->addAddress($to);
        $mailer->Subject = $subject;
        $mailer->Body = $body;
        $mailer->isHTML(false);

        if (SMTP_HOST !== '') {
            $mailer->isSMTP();
            $mailer->Host = SMTP_HOST;
            $mailer->Port = SMTP_PORT;
            $mailer->SMTPAuth = SMTP_USER !== '';
            if ($mailer->SMTPAuth) {
                $mailer->Username = SMTP_USER;
                $mailer->Password = SMTP_PASS;
            }
            $mailer->SMTPSecure = SMTP_PORT === 465
                ? \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS
                : \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
        } else {
            $mailer->isMail();
        }

        return $mailer->send();
    } catch (Throwable $error) {
        error_log('Email delivery failed: ' . $error->getMessage());
        return false;
    }
}

function login_otp_rate_limited(int $userId): bool {
    $stmt = db()->prepare('SELECT COUNT(*) FROM login_history WHERE user_id = ? AND status IN ("OTP sent", "OTP delivery failed") AND created_at >= DATE_SUB(NOW(), INTERVAL 10 MINUTE)');
    $stmt->execute([$userId]);
    return (int) $stmt->fetchColumn() >= 4;
}

function ensure_profile_photo_column(): void {
    if (defined('DB_DRIVER') && DB_DRIVER === 'pgsql') {
        return;
    }
    static $checked = false;
    if ($checked) {
        return;
    }
    $column = db()->query("SHOW COLUMNS FROM users LIKE 'profile_photo'")->fetch();
    if (!$column) {
        db()->exec('ALTER TABLE users ADD COLUMN profile_photo VARCHAR(255) DEFAULT NULL');
    }
    $checked = true;
}

function ensure_assigned_roles_column(): void {
    static $checked = false;
    if ($checked) {
        return;
    }
    try {
        if (defined('DB_DRIVER') && DB_DRIVER === 'pgsql') {
            db()->exec('ALTER TABLE users ADD COLUMN IF NOT EXISTS assigned_roles TEXT DEFAULT NULL');
        } else {
            $column = db()->query("SHOW COLUMNS FROM users LIKE 'assigned_roles'")->fetch();
            if (!$column) {
                db()->exec('ALTER TABLE users ADD COLUMN assigned_roles TEXT DEFAULT NULL AFTER role');
            }
        }
    } catch (Throwable) {
    }
    $checked = true;
}

function ensure_activity_date_to_column(): void {
    static $checked = false;
    if ($checked) {
        return;
    }
    try {
        if (defined('DB_DRIVER') && DB_DRIVER === 'pgsql') {
            db()->exec('ALTER TABLE activities ADD COLUMN IF NOT EXISTS date_to DATE DEFAULT NULL');
        } else {
            $column = db()->query("SHOW COLUMNS FROM activities LIKE 'date_to'")->fetch();
            if (!$column) {
                db()->exec('ALTER TABLE activities ADD COLUMN date_to DATE DEFAULT NULL AFTER date');
            }
            db()->exec('UPDATE activities SET date_to = date WHERE date_to IS NULL');
        }
    } catch (Throwable) {
    }
    $checked = true;
}

function ensure_activity_time_columns(): void {
    static $checked = false;
    if ($checked) {
        return;
    }
    try {
        if (defined('DB_DRIVER') && DB_DRIVER === 'pgsql') {
            db()->exec('ALTER TABLE activities ADD COLUMN IF NOT EXISTS start_time VARCHAR(60) DEFAULT NULL');
            db()->exec('ALTER TABLE activities ADD COLUMN IF NOT EXISTS end_time VARCHAR(60) DEFAULT NULL');
            db()->exec('ALTER TABLE activities ALTER COLUMN start_time TYPE VARCHAR(60)');
            db()->exec('ALTER TABLE activities ALTER COLUMN end_time TYPE VARCHAR(60)');
        } else {
            $columnStart = db()->query("SHOW COLUMNS FROM activities LIKE 'start_time'")->fetch();
            if (!$columnStart) {
                db()->exec('ALTER TABLE activities ADD COLUMN start_time VARCHAR(60) DEFAULT NULL AFTER date_to');
            } else {
                db()->exec('ALTER TABLE activities MODIFY COLUMN start_time VARCHAR(60) DEFAULT NULL');
            }
            $columnEnd = db()->query("SHOW COLUMNS FROM activities LIKE 'end_time'")->fetch();
            if (!$columnEnd) {
                db()->exec('ALTER TABLE activities ADD COLUMN end_time VARCHAR(60) DEFAULT NULL AFTER start_time');
            } else {
                db()->exec('ALTER TABLE activities MODIFY COLUMN end_time VARCHAR(60) DEFAULT NULL');
            }
        }
    } catch (Throwable) {
    }
    $checked = true;
}

function format_activity_time(?string $startTime, ?string $endTime = null): string {
    $startTime = trim((string) $startTime);
    $endTime = trim((string) $endTime);
    if ($startTime === '') {
        return '';
    }
    $ts1 = strtotime('2000-01-01 ' . $startTime);
    $t1 = $ts1 !== false ? date('g:i A', $ts1) : $startTime;
    if ($endTime === '') {
        return $t1;
    }
    $ts2 = strtotime('2000-01-01 ' . $endTime);
    $t2 = $ts2 !== false ? date('g:i A', $ts2) : $endTime;
    return $t1 . ' – ' . $t2;
}

function ensure_activity_archived_at_column(): void {
    if (defined('DB_DRIVER') && DB_DRIVER === 'pgsql') {
        return;
    }
    static $checked = false;
    if ($checked) {
        return;
    }
    $column = db()->query("SHOW COLUMNS FROM activities LIKE 'archived_at'")->fetch();
    if (!$column) {
        db()->exec('ALTER TABLE activities ADD COLUMN archived_at DATETIME DEFAULT NULL');
    }
    $checked = true;
}

if (!function_exists('ensure_research_archived_at_column')) {
function ensure_research_archived_at_column(): void {
    static $checked = false;
    if ($checked) {
        return;
    }
    try {
        if (defined('DB_DRIVER') && DB_DRIVER === 'pgsql') {
            db()->exec('ALTER TABLE research_projects ADD COLUMN IF NOT EXISTS archived_at TIMESTAMPTZ DEFAULT NULL');
        } else {
            $column = db()->query("SHOW COLUMNS FROM research_projects LIKE 'archived_at'")->fetch();
            if (!$column) {
                db()->exec('ALTER TABLE research_projects ADD COLUMN archived_at DATETIME DEFAULT NULL');
            }
        }
    } catch (Throwable $e) {}
    $checked = true;
}
}

if (!function_exists('ensure_extension_archived_at_column')) {
function ensure_extension_archived_at_column(): void {
    static $checked = false;
    if ($checked) {
        return;
    }
    try {
        if (defined('DB_DRIVER') && DB_DRIVER === 'pgsql') {
            db()->exec('ALTER TABLE extension_projects ADD COLUMN IF NOT EXISTS archived_at TIMESTAMPTZ DEFAULT NULL');
        } else {
            $column = db()->query("SHOW COLUMNS FROM extension_projects LIKE 'archived_at'")->fetch();
            if (!$column) {
                db()->exec('ALTER TABLE extension_projects ADD COLUMN archived_at DATETIME DEFAULT NULL');
            }
        }
    } catch (Throwable $e) {}
    $checked = true;
}
}

function ensure_extension_proposal_columns(): void {
    if (defined('DB_DRIVER') && DB_DRIVER === 'pgsql') {
        return;
    }
    static $checked = false;
    if ($checked) {
        return;
    }
    $columns = [
        'academic_year' => 'VARCHAR(50)',
        'needs_assessment' => 'TEXT',
        'moa_mou' => 'TEXT',
        'implementation_details' => 'TEXT',
        'outputs' => 'TEXT',
        'outcome' => 'TEXT',
        'impact_assessment' => 'TEXT',
        'sustainability' => 'TEXT',
        'evidence_checklist' => 'TEXT',
        'accomplishment_report' => 'TEXT'
    ];
    try {
        if (defined('DB_DRIVER') && DB_DRIVER === 'pgsql') {
            foreach ($columns as $col => $type) {
                db()->exec("ALTER TABLE extension_projects ADD COLUMN IF NOT EXISTS {$col} {$type}");
            }
        } else {
            foreach ($columns as $col => $type) {
                $exists = db()->query("SHOW COLUMNS FROM extension_projects LIKE '{$col}'")->fetch();
                if (!$exists) {
                    db()->exec("ALTER TABLE extension_projects ADD COLUMN {$col} {$type}");
                }
            }
        }
    } catch (Throwable $e) {
        // Silently continue if permissions or already exists
    }
    $checked = true;
}

function ensure_system_settings_table(): void {
    static $checked = false;
    if ($checked) {
        return;
    }
    try {
        if (defined('DB_DRIVER') && DB_DRIVER === 'pgsql') {
            db()->exec("CREATE TABLE IF NOT EXISTS system_settings (
                setting_key VARCHAR(100) PRIMARY KEY,
                setting_value TEXT,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )");
        } else {
            db()->exec("CREATE TABLE IF NOT EXISTS system_settings (
                setting_key VARCHAR(100) PRIMARY KEY,
                setting_value TEXT,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        }

        // Check if seeded
        $cnt = (int) db()->query("SELECT COUNT(*) FROM system_settings")->fetchColumn();
        if ($cnt === 0) {
            $defaults = [
                'strong_passwords' => '1',
                'session_timeout_enabled' => '1',
                'session_timeout_minutes' => '30',
                'max_file_size_mb' => '25',
                'allowed_file_types' => 'PDF, DOCX, XLSX, PPTX',
                'require_admin_remarks' => '1',
                'notify_on_approval' => '1',
            ];
            $stmt = db()->prepare("INSERT INTO system_settings (setting_key, setting_value) VALUES (?, ?)");
            foreach ($defaults as $k => $v) {
                $stmt->execute([$k, $v]);
            }
        }
    } catch (Throwable $e) {
        error_log('ensure_system_settings_table error: ' . $e->getMessage());
    }
    $checked = true;
}

function &get_system_settings_cache(): array {
    static $cache = null;
    if ($cache === null) {
        $cache = [];
        try {
            $rows = db()->query("SELECT setting_key, setting_value FROM system_settings")->fetchAll();
            foreach ($rows as $row) {
                $cache[$row['setting_key']] = (string) $row['setting_value'];
            }
        } catch (Throwable $e) {
            ensure_system_settings_table();
            try {
                $rows = db()->query("SELECT setting_key, setting_value FROM system_settings")->fetchAll();
                foreach ($rows as $row) {
                    $cache[$row['setting_key']] = (string) $row['setting_value'];
                }
            } catch (Throwable) {
                // retain fallback
            }
        }
    }
    return $cache;
}

function get_system_setting(string $key, mixed $default = null): mixed {
    $cache = get_system_settings_cache();
    return array_key_exists($key, $cache) ? $cache[$key] : $default;
}

function set_system_setting(string $key, mixed $value): bool {
    ensure_system_settings_table();
    $valStr = (string) $value;
    try {
        if (defined('DB_DRIVER') && DB_DRIVER === 'pgsql') {
            $stmt = db()->prepare("INSERT INTO system_settings (setting_key, setting_value, updated_at) VALUES (?, ?, NOW()) ON CONFLICT (setting_key) DO UPDATE SET setting_value = EXCLUDED.setting_value, updated_at = NOW()");
        } else {
            $stmt = db()->prepare("INSERT INTO system_settings (setting_key, setting_value, updated_at) VALUES (?, ?, NOW()) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), updated_at = NOW()");
        }
        $res = $stmt->execute([$key, $valStr]);
        $cache = &get_system_settings_cache();
        $cache[$key] = $valStr;
        return (bool) $res;
    } catch (Throwable $e) {
        error_log('set_system_setting error: ' . $e->getMessage());
        return false;
    }
}

function app_max_upload_bytes(): int {
    $mb = (int) get_system_setting('max_file_size_mb', 25);
    if ($mb <= 0) {
        $mb = 25;
    }
    return $mb * 1024 * 1024;
}

function app_allowed_extensions(): array {
    $raw = (string) get_system_setting('allowed_file_types', 'PDF, DOCX, XLSX, PPTX');
    $parts = array_filter(array_map('trim', explode(',', strtolower($raw))));
    return $parts ?: ['pdf', 'docx', 'xlsx', 'pptx'];
}

function h($value): string {
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function csrf_token(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_field(): string {
    return '<input type="hidden" name="csrf_token" value="' . h(csrf_token()) . '">';
}

function verify_csrf(): void {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $token = $_POST['csrf_token'] ?? '';
        if (!hash_equals($_SESSION['csrf_token'] ?? '', (string) $token)) {
            http_response_code(419);
            exit('Invalid CSRF token.');
        }
    }
}

function redirect_to(string $path): never {
    header('Location: ' . $path);
    exit;
}

function flash(?string $type = null, ?string $message = null): ?array {
    if ($type !== null && $message !== null) {
        $_SESSION['flash'] = ['type' => $type, 'message' => $message];
        return null;
    }
    $flash = $_SESSION['flash'] ?? null;
    unset($_SESSION['flash']);
    return $flash;
}

function current_user(): ?array {
    if (empty($_SESSION['user_id'])) {
        return null;
    }
    $timeoutEnabled = get_system_setting('session_timeout_enabled', '1') === '1';
    $timeoutMinutes = (int) get_system_setting('session_timeout_minutes', 30);
    $timeoutSeconds = ($timeoutMinutes > 0 ? $timeoutMinutes : 30) * 60;
    if ($timeoutEnabled && !empty($_SESSION['last_activity']) && time() - (int) $_SESSION['last_activity'] > $timeoutSeconds) {
        session_unset();
        session_destroy();
        return null;
    }
    $_SESSION['last_activity'] = time();

    ensure_assigned_roles_column();

    $stmt = db()->prepare("SELECT * FROM users WHERE id = ? AND status = 'Active' AND deleted_at IS NULL LIMIT 1");
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch() ?: null;
    if (!$user) {
        return null;
    }

    $user['primary_role'] = (string) ($user['role'] ?? '');
    if (!empty($_SESSION['active_role'])) {
        $availableRoles = user_available_roles($user);
        if (in_array($_SESSION['active_role'], $availableRoles, true)) {
            $user['role'] = (string) $_SESSION['active_role'];
            $user['active_role'] = (string) $_SESSION['active_role'];
        } else {
            unset($_SESSION['active_role']);
            $user['active_role'] = $user['primary_role'];
        }
    } else {
        $user['active_role'] = $user['primary_role'];
    }

    return $user;
}

function role_permissions(): array {
    return [
        ROLE_SUPER_ADMIN => ['dashboard', 'audit', 'settings', 'backup', 'users'],
        ROLE_OVPRE_ADMIN => ['dashboard', 'documents', 'users', 'pending-users', 'research', 'extension', 'reports', 'activities', 'announcements', 'audit', 'settings', 'backup'],
        ROLE_RESEARCH_DIRECTOR => ['dashboard', 'documents', 'users', 'pending-users', 'research', 'reports', 'activities', 'announcements', 'audit', 'settings', 'backup', 'status', 'analytics'],
        ROLE_EXTENSION_DIRECTOR => ['dashboard', 'extension', 'analytics', 'reports', 'announcements', 'activities', 'status'],
        ROLE_RESEARCH_COORDINATOR => ['dashboard', 'research', 'analytics', 'reports', 'announcements', 'activities', 'status'],
        ROLE_EXTENSION_COORDINATOR => ['dashboard', 'extension', 'analytics', 'reports', 'announcements', 'activities', 'status'],
        ROLE_FACULTY_EXTENSIONIST => ['dashboard', 'extension', 'status'],
        ROLE_RESEARCHER => ['dashboard', 'research', 'status'],
    ];
}

function role_can(string $role, string $permission): bool {
    $role = role_access_group($role);
    $permissions = role_permissions()[$role] ?? [];
    return in_array('*', $permissions, true) || in_array($permission, $permissions, true);
}

function require_login(array $roles = []): array {
    $user = current_user();
    if (!$user) {
        redirect_to('index.php');
    }
    $userRole = (string) ($user['role'] ?? '');
    $userGroup = role_access_group($userRole);
    $allowedRoles = array_map('role_access_group', $roles);
    if (role_dashboard_path($userRole) === null || (!empty($roles) && !in_array($userGroup, $allowedRoles, true))) {
        // Active role is not authorized for this specific page.
        // DO NOT silently mutate $_SESSION['user_id'] or revert the user account!
        $dashboardPath = role_dashboard_path($userRole) ?? 'dashboard.php';
        flash('error', 'You do not have access to that feature with your active role (' . h($userRole) . ').');

        $currentScript = basename($_SERVER['PHP_SELF'] ?? '');
        $targetScript = basename($dashboardPath);
        if ($currentScript !== $targetScript) {
            redirect_to($dashboardPath);
        }

        http_response_code(403);
        if (file_exists(__DIR__ . '/../403.php')) {
            require __DIR__ . '/../403.php';
            exit;
        }
        exit('You do not have access to this page.');
    }
    return $user;
}

function has_role(string|array $roles): bool {
    $user = current_user();
    return $user !== null && in_array($user['role'] ?? '', (array) $roles, true);
}

function require_role(array $roles): array {
    return require_login($roles);
}

function require_super_admin(): array {
    return require_login([ROLE_SUPER_ADMIN, ROLE_OVPRE_ADMIN, ROLE_RESEARCH_DIRECTOR]);
}

function log_action(?int $userId, string $action): void {
    $stmt = db()->prepare('INSERT INTO audit_logs (user_id, action, ip_address, created_at) VALUES (?, ?, ?, NOW())');
    $stmt->execute([$userId, $action, $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1']);
}

function notification_link_column_exists(): bool {
    static $exists = null;
    if ($exists !== null) {
        return $exists;
    }
    try {
        if (defined('DB_DRIVER') && DB_DRIVER === 'pgsql') {
            $stmt = db()->query("SELECT 1 FROM information_schema.columns WHERE table_schema = current_schema() AND table_name = 'notifications' AND column_name = 'link' LIMIT 1");
            $exists = (bool) $stmt->fetchColumn();
        } else {
            $stmt = db()->query("SHOW COLUMNS FROM notifications LIKE 'link'");
            $exists = (bool) $stmt->fetch();
        }
    } catch (Throwable $error) {
        error_log('Could not check the notifications.link column: ' . $error->getMessage());
        $exists = false;
    }
    return $exists;
}

function notify_user(int $userId, string $message, ?string $link = null): void {
    if (notification_link_column_exists()) {
        $stmt = db()->prepare('INSERT INTO notifications (user_id, message, status, created_at, link) VALUES (?, ?, ?, NOW(), ?)');
        $stmt->execute([$userId, $message, 'Unread', $link]);
        return;
    }
    $stmt = db()->prepare('INSERT INTO notifications (user_id, message, status, created_at) VALUES (?, ?, ?, NOW())');
    $stmt->execute([$userId, $message, 'Unread']);
}

function notify_all_accounts(string $message, ?string $link = null, ?int $excludeUserId = null): void {
    $sql = 'SELECT id FROM users WHERE status = ? AND deleted_at IS NULL';
    $params = ['Active'];
    if ($excludeUserId !== null) {
        $sql .= ' AND id <> ?';
        $params[] = $excludeUserId;
    }
    $users = db()->prepare($sql);
    $users->execute($params);
    foreach ($users->fetchAll(PDO::FETCH_COLUMN) as $userId) {
        notify_user((int) $userId, $message, $link);
    }
}

function log_review(string $entityType, int $entityId, ?int $userId, string $action, string $remarks = ''): void {
    $stmt = db()->prepare('INSERT INTO review_logs (entity_type, entity_id, user_id, action, remarks, created_at) VALUES (?, ?, ?, ?, ?, NOW())');
    $stmt->execute([$entityType, $entityId, $userId, $action, $remarks]);
}

function review_timeline(string $entityType, int $entityId): array {
    $stmt = db()->prepare('SELECT r.*, u.fullname FROM review_logs r LEFT JOIN users u ON u.id = r.user_id WHERE r.entity_type = ? AND r.entity_id = ? ORDER BY r.created_at DESC');
    $stmt->execute([$entityType, $entityId]);
    return $stmt->fetchAll();
}

function log_login_event(?int $userId, string $username, string $status): void {
    $userId = ($userId !== null && $userId > 0) ? $userId : null;
    $stmt = db()->prepare('INSERT INTO login_history (user_id, username, status, ip_address, user_agent, created_at) VALUES (?, ?, ?, ?, ?, NOW())');
    $stmt->execute([$userId, $username, $status, $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1', substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255)]);
}

function assignable_users(): array {
    return db()->query("SELECT id, fullname, role FROM users WHERE status = 'Active' AND deleted_at IS NULL ORDER BY fullname")->fetchAll();
}

function statusClass($status): string {
    return strtolower(str_replace([' ', '/'], '-', (string) $status));
}

function departments(): array {
    try {
        return db()->query('SELECT * FROM departments ORDER BY department_name')->fetchAll();
    } catch (Throwable) {
        return [
            ['id' => 1, 'department_name' => 'College of Computer Studies (CCS)'],
            ['id' => 2, 'department_name' => 'College of Engineering (COE)'],
            ['id' => 3, 'department_name' => 'College of Nursing and Allied Health Sciences (CONAHS)'],
            ['id' => 4, 'department_name' => 'College of Arts and Sciences (CAS)'],
            ['id' => 5, 'department_name' => 'College of Business Administration (CBA)'],
            ['id' => 6, 'department_name' => 'College of Teacher Education (CTE)'],
            ['id' => 7, 'department_name' => 'College of Tourism and Hospitality Management (CTHM)'],
            ['id' => 8, 'department_name' => 'College of Accountancy (COA)'],
            ['id' => 9, 'department_name' => 'College of Public Administration and Governance (CPAG)'],
            ['id' => 10, 'department_name' => 'College of Human Kinetics (CHK)'],
        ];
    }
}

function categories(): array {
    try {
        return db()->query('SELECT * FROM categories ORDER BY category_name')->fetchAll();
    } catch (Throwable) {
        return [
            ['id' => 1, 'category_name' => 'Research Proposal'],
            ['id' => 2, 'category_name' => 'Extension Proposal'],
            ['id' => 3, 'category_name' => 'Accomplishment Report'],
            ['id' => 4, 'category_name' => 'Institutional Document'],
        ];
    }
}

function dashboard_stats(): array {
    return [
        'total_records' => (int) db()->query('SELECT COUNT(*) FROM documents')->fetchColumn(),
        'active_records' => (int) db()->query('SELECT COUNT(*) FROM documents WHERE archived_at IS NULL')->fetchColumn(),
        'archived_records' => (int) db()->query('SELECT COUNT(*) FROM documents WHERE archived_at IS NOT NULL')->fetchColumn(),
        'users' => (int) db()->query('SELECT COUNT(*) FROM users')->fetchColumn(),
        'pending' => (int) db()->query('SELECT COUNT(*) FROM documents WHERE status = "Pending"')->fetchColumn(),
    ];
}

function document_list(bool $archived = false, string $status = 'All'): array {
    $where = 'd.archived_at IS ' . ($archived ? 'NOT NULL' : 'NULL');
    $params = [];
    if ($status !== 'All' && in_array($status, ['Pending', 'Under Review', 'Approved', 'Declined'], true)) {
        $where .= ' AND d.status = ?';
        $params[] = $status;
    }
    $sql = 'SELECT d.*, c.category_name, u.fullname AS uploader, dep.department_name, au.fullname AS assignee
            FROM documents d
            JOIN categories c ON c.id = d.category_id
            JOIN users u ON u.id = d.uploaded_by
            LEFT JOIN users au ON au.id = d.assigned_to
            JOIN departments dep ON dep.id = d.department_id
            WHERE ' . $where . '
            ORDER BY d.created_at DESC';
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

function save_uploaded_document(array $post, array $file, int $userId): void {
    $mimeMap = [
        'pdf' => 'application/pdf',
        'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'pptx' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
        'doc' => 'application/msword',
        'xls' => 'application/vnd.ms-excel',
        'ppt' => 'application/vnd.ms-powerpoint',
        'png' => 'image/png',
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'zip' => 'application/zip',
    ];
    $allowedExts = app_allowed_extensions();
    $maxBytes = app_max_upload_bytes();
    $maxMb = round($maxBytes / (1024 * 1024));

    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        throw new RuntimeException('Please choose a valid file.');
    }
    if (($file['size'] ?? 0) > $maxBytes) {
        throw new RuntimeException("File exceeds the {$maxMb} MB limit.");
    }

    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, $allowedExts, true)) {
        throw new RuntimeException('Only ' . strtoupper(implode(', ', $allowedExts)) . ' files are accepted.');
    }

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->file($file['tmp_name']);
    if ($mime !== $allowed[$ext] && $ext !== 'docx' && $ext !== 'xlsx' && $ext !== 'pptx') {
        throw new RuntimeException('The uploaded file type could not be verified.');
    }

    $category = (int) $post['category_id'];
    $department = (int) $post['department_id'];
    $stored = bin2hex(random_bytes(16)) . '.' . $ext;
    $objectPath = $category . '/' . $stored;
    supabase_storage_upload((string) $file['tmp_name'], $objectPath, (string) ($mime ?: $allowed[$ext]));
    $storedName = 'storage:' . $objectPath;

    $stmt = db()->prepare('INSERT INTO documents (title, category_id, description, author, department_id, filename, original_filename, uploaded_by, status, submission_date, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, "Pending", ?, NOW())');
    $stmt->execute([
        trim($post['title']),
        $category,
        trim($post['description'] ?? ''),
        trim($post['author']),
        $department,
        $storedName,
        $file['name'],
        $userId,
        $post['submission_date'] ?: date('Y-m-d'),
    ]);
    $documentId = (int) db()->lastInsertId();
    db()->prepare('INSERT INTO document_versions (document_id, version_label, filename, modified_by, created_at) VALUES (?, "Original Version", ?, ?, NOW())')
        ->execute([$documentId, $storedName, $userId]);
    log_action($userId, 'Uploaded document: ' . trim($post['title']));
    notify_all_accounts('New file uploaded: ' . trim($post['title']), 'submitted-files.php#document-' . $documentId);
}

function record_access_url(string $filename, string $localPrefix = '', ?int $documentId = null, ?int $versionId = null): string
{
    if (!$documentId) {
        throw new InvalidArgumentException('A document reference is required for a secure file link.');
    }
    return $localPrefix . 'download-document.php?id=' . $documentId . ($versionId ? '&version=' . $versionId : '');
}

if (!function_exists('record_is_previewable')) {
    function record_is_previewable(string $filename): bool
    {
        return in_array(strtolower(pathinfo(preg_replace('/^storage:/', '', $filename), PATHINFO_EXTENSION)), ['pdf', 'jpg', 'jpeg', 'png', 'webp', 'svg'], true);
    }
}

