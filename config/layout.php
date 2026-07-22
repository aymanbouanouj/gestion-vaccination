<?php

declare(strict_types=1);

require_once __DIR__ . '/application.php';

function vaccinationAssetPath(string $path): string
{
    return VACCINATION_BASE_PATH . '/assets/' . ltrim($path, '/');
}

function vaccinationCurrentPageLabel(string $currentPage, string $fallbackHeading): string
{
    $labels = [
        'dashboard' => 'Tableau de bord',
        'citizen-create' => 'Inscrire un citoyen',
        'centre-lookup' => 'Rechercher un centre',
        'activity' => 'Activite des vaccinateurs',
        'password' => 'Mot de passe',
    ];

    return $labels[$currentPage] ?? $fallbackHeading;
}

/**
 * @return array<string, array{label:string, href:string}>
 */
function vaccinationBusinessNavigationItems(): array
{
    return [
        'dashboard' => [
            'label' => 'Tableau de bord',
            'href' => VACCINATION_DASHBOARD_PATH,
        ],
        'citizen-create' => [
            'label' => 'Inscrire un citoyen',
            'href' => VACCINATION_BASE_PATH . '/pages/formAjout.php',
        ],
        'centre-lookup' => [
            'label' => 'Rechercher un centre',
            'href' => VACCINATION_BASE_PATH . '/pages/centreVaccination.php',
        ],
        'activity' => [
            'label' => 'Activite des vaccinateurs',
            'href' => VACCINATION_BASE_PATH . '/pages/performanceVaccinateur.php',
        ],
    ];
}

/**
 * @return array<string, array{label:string, href:string}>
 */
function vaccinationAccountNavigationItems(): array
{
    return [
        'password' => [
            'label' => 'Mot de passe',
            'href' => VACCINATION_PASSWORD_CHANGE_PATH,
        ],
    ];
}

function vaccinationAdministratorDisplayNameRaw(): string
{
    if (!isAuthenticated()) {
        return '';
    }

    $administrator = $_SESSION[VACCINATION_SESSION_KEY] ?? null;
    if (!is_array($administrator) || !is_string($administrator['display_name'] ?? null)) {
        return '';
    }

    return trim($administrator['display_name']);
}

function vaccinationAdministratorInitials(): string
{
    $name = vaccinationAdministratorDisplayNameRaw();
    if ($name === '') {
        return 'AD';
    }

    $parts = preg_split('/\s+/u', $name);
    if (!is_array($parts)) {
        return 'AD';
    }

    $initials = '';
    foreach ($parts as $part) {
        if (!is_string($part) || $part === '') {
            continue;
        }

        $initials .= function_exists('mb_substr')
            ? mb_substr($part, 0, 1, 'UTF-8')
            : substr($part, 0, 1);

        if (strlen($initials) >= 2) {
            break;
        }
    }

    if ($initials === '') {
        return 'AD';
    }

    return function_exists('mb_strtoupper')
        ? mb_strtoupper($initials, 'UTF-8')
        : strtoupper($initials);
}

function renderInlineIcon(string $name, bool $decorative = true, string $label = ''): void
{
    $icons = [
        'menu' => '<path d="M4 7h16"/><path d="M4 12h16"/><path d="M4 17h16"/>',
        'citizen' => '<path d="M12 12a3.5 3.5 0 1 0 0-7 3.5 3.5 0 0 0 0 7Z"/><path d="M5 19a7 7 0 0 1 14 0"/>',
        'centre' => '<path d="M12 20s6-5.4 6-10a6 6 0 1 0-12 0c0 4.6 6 10 6 10Z"/><path d="M12 11.5a1.75 1.75 0 1 0 0-3.5 1.75 1.75 0 0 0 0 3.5Z"/>',
        'report' => '<path d="M6 18V9"/><path d="M12 18V6"/><path d="M18 18v-4"/><path d="M4 18h16"/>',
        'password' => '<rect x="5" y="11" width="14" height="9" rx="2"/><path d="M8 11V8.5a4 4 0 1 1 8 0V11"/><path d="M12 15.5v2"/>',
        'shield' => '<path d="M12 3l7 3v5c0 4.2-2.7 7.9-7 10-4.3-2.1-7-5.8-7-10V6l7-3Z"/><path d="m9.5 12.5 1.7 1.8 3.5-4"/>',
        'logout' => '<path d="M10 6H6a2 2 0 0 0-2 2v8a2 2 0 0 0 2 2h4"/><path d="M14 16l4-4-4-4"/><path d="M18 12H9"/>',
        'validation' => '<path d="M5 12.5 9.5 17 19 7"/><path d="M19 12v6H5V6h9"/>',
        'responsive' => '<rect x="4" y="5" width="12" height="11" rx="2"/><path d="M18 9h2a1 1 0 0 1 1 1v6a1 1 0 0 1-1 1h-5"/>',
        'data' => '<ellipse cx="12" cy="6" rx="6.5" ry="2.5"/><path d="M5.5 6v6c0 1.4 2.9 2.5 6.5 2.5s6.5-1.1 6.5-2.5V6"/><path d="M5.5 12v6c0 1.4 2.9 2.5 6.5 2.5s6.5-1.1 6.5-2.5v-6"/>',
        'arrow' => '<path d="M5 12h14"/><path d="m13 6 6 6-6 6"/>',
    ];

    $paths = $icons[$name] ?? $icons['shield'];
    $attributes = $decorative
        ? 'aria-hidden="true" focusable="false"'
        : 'role="img" aria-label="' . escapeHtml($label) . '"';
    ?>
    <svg class="app-icon-svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" <?php echo $attributes; ?>>
        <?php echo $paths; ?>
    </svg>
    <?php
}

/**
 * Render the shared document header and open the main content landmark.
 */
function renderPageStart(
    string $title,
    string $heading,
    string $currentPage = '',
    bool $showNavigation = true,
    string $bodyClass = '',
    string $mainModifier = '',
    ?string $intro = null
): void {
    $documentTitle = escapeHtml($title . ' - Gestion Vaccination');
    $bodyClasses = trim('app-body ' . $bodyClass);
    $mainClasses = trim('page-shell ' . $mainModifier);
    $logoIcon = vaccinationAssetPath('branding/logo-icon-64.png');
    $logoFullDark = vaccinationAssetPath('branding/logo-full-dark.png');
    $favicon32 = vaccinationAssetPath('branding/favicon-32x32.png');
    $favicon16 = vaccinationAssetPath('branding/favicon-16x16.png');
    $faviconIco = vaccinationAssetPath('branding/favicon.ico');
    $appleTouch = vaccinationAssetPath('branding/apple-touch-icon.png');
    $stylesheet = vaccinationAssetPath('css/style.css');
    $script = vaccinationAssetPath('js/app.js');
    $currentPageLabel = vaccinationCurrentPageLabel($currentPage, $heading);
    $showApplicationNavigation = $showNavigation && isAuthenticated();
    $headerClasses = $showApplicationNavigation ? 'site-header' : 'site-header site-header--minimal';
    ?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="Interface locale de demonstration pour la gestion de la vaccination.">
    <title><?php echo $documentTitle; ?></title>
    <link rel="icon" type="image/png" sizes="32x32" href="<?php echo escapeHtml($favicon32); ?>">
    <link rel="icon" type="image/png" sizes="16x16" href="<?php echo escapeHtml($favicon16); ?>">
    <link rel="icon" href="<?php echo escapeHtml($faviconIco); ?>">
    <link rel="apple-touch-icon" sizes="180x180" href="<?php echo escapeHtml($appleTouch); ?>">
    <link rel="stylesheet" href="<?php echo escapeHtml($stylesheet); ?>">
</head>
<body class="<?php echo escapeHtml($bodyClasses); ?>">
<div class="site-shell">
<a class="skip-link" href="#contenu-principal">Aller au contenu principal</a>

<header class="<?php echo escapeHtml($headerClasses); ?>" data-site-header>
    <div class="site-header__inner">
        <div class="site-header__bar">
            <div class="site-header__brand-zone">
                <?php if ($showNavigation && isAuthenticated() && !administratorMustChangePassword()): ?>
                    <a class="site-brand" href="<?php echo escapeHtml(VACCINATION_DASHBOARD_PATH); ?>">
                        <span class="site-brand__mark">
                            <img
                                class="site-brand__logo"
                                src="<?php echo escapeHtml($logoIcon); ?>"
                                alt="Logo Gestion Vaccination"
                                width="40"
                                height="40"
                            >
                        </span>
                        <span class="site-brand__text">
                            <span class="site-brand__eyebrow">Prototype pedagogique local</span>
                            <span class="site-brand__name">Gestion Vaccination</span>
                        </span>
                    </a>
                <?php else: ?>
                    <div class="site-brand">
                        <span class="site-brand__mark">
                            <img
                                class="site-brand__logo"
                                src="<?php echo escapeHtml($logoIcon); ?>"
                                alt="Logo Gestion Vaccination"
                                width="40"
                                height="40"
                            >
                        </span>
                        <span class="site-brand__text">
                            <span class="site-brand__eyebrow">Prototype pedagogique local</span>
                            <span class="site-brand__name">Gestion Vaccination</span>
                        </span>
                    </div>
                <?php endif; ?>
            </div>

            <?php if ($showApplicationNavigation): ?>
                <div class="site-header__nav-zone">
                    <?php renderApplicationNavigation($currentPage, false); ?>
                </div>

                <div class="site-header__account-zone">
                    <div class="site-account-panel">
                        <span class="site-account-panel__avatar" aria-hidden="true"><?php echo escapeHtml(vaccinationAdministratorInitials()); ?></span>
                        <div class="site-account-panel__copy">
                            <span class="site-account-panel__eyebrow">Session administrateur</span>
                            <strong><?php echo authenticatedAdministratorDisplayName(); ?></strong>
                        </div>
                        <div class="site-account-panel__actions">
                            <a
                                class="header-action"
                                href="<?php echo escapeHtml(VACCINATION_PASSWORD_CHANGE_PATH); ?>"
                                <?php echo $currentPage === 'password' ? 'aria-current="page"' : ''; ?>
                            >Mot de passe</a>
                            <?php renderLogoutControl('header-action header-action--destructive'); ?>
                        </div>
                    </div>
                </div>

                <button
                    class="nav-toggle"
                    type="button"
                    aria-expanded="false"
                    aria-controls="primary-navigation-panel"
                    data-nav-toggle
                    hidden
                >
                    <span class="nav-toggle__icon"><?php renderInlineIcon('menu'); ?></span>
                    <span>Menu</span>
                </button>
            <?php endif; ?>
        </div>

        <?php if ($showApplicationNavigation): ?>
            <div class="nav-drawer-backdrop" data-nav-backdrop hidden></div>
            <div class="nav-drawer" id="primary-navigation-panel" data-nav-panel hidden tabindex="-1" aria-label="Navigation mobile">
                <div class="nav-drawer__header">
                    <div class="nav-drawer__brand">
                        <img
                            class="nav-drawer__logo"
                            src="<?php echo escapeHtml($logoFullDark); ?>"
                            alt="Identite visuelle Gestion Vaccination"
                            width="80"
                            height="80"
                        >
                        <div class="nav-drawer__copy">
                            <span class="nav-drawer__eyebrow">Page active</span>
                            <strong><?php echo escapeHtml($currentPageLabel); ?></strong>
                            <p>Session administrateur locale.</p>
                        </div>
                    </div>

                    <div class="nav-drawer__account">
                        <span class="site-account-panel__avatar" aria-hidden="true"><?php echo escapeHtml(vaccinationAdministratorInitials()); ?></span>
                        <div>
                            <span class="site-account-panel__eyebrow">Administrateur</span>
                            <strong><?php echo authenticatedAdministratorDisplayName(); ?></strong>
                        </div>
                    </div>
                </div>

                <?php renderApplicationNavigation($currentPage, true); ?>
            </div>
        <?php endif; ?>
    </div>
</header>

<main id="contenu-principal" class="<?php echo escapeHtml($mainClasses); ?>" tabindex="-1">
    <header class="page-heading">
        <p class="page-kicker">Prototype pedagogique local</p>
        <h1><?php echo escapeHtml($heading); ?></h1>
        <?php if ($intro !== null && $intro !== ''): ?>
            <p class="page-intro"><?php echo escapeHtml($intro); ?></p>
        <?php endif; ?>
    </header>
    <?php
}

function renderApplicationNavigation(string $currentPage, bool $mobile = false): void
{
    $items = administratorMustChangePassword()
        ? vaccinationAccountNavigationItems()
        : ($mobile
            ? array_merge(vaccinationBusinessNavigationItems(), vaccinationAccountNavigationItems())
            : vaccinationBusinessNavigationItems());
    $navClass = $mobile ? 'primary-nav primary-nav--mobile' : 'primary-nav primary-nav--desktop';
    ?>
    <nav class="<?php echo escapeHtml($navClass); ?>" aria-label="Navigation principale">
        <ul>
            <?php foreach ($items as $key => $item): ?>
                <li>
                    <a class="nav-link" href="<?php echo escapeHtml($item['href']); ?>" <?php echo $currentPage === $key ? 'aria-current="page"' : ''; ?>><?php echo escapeHtml($item['label']); ?></a>
                </li>
            <?php endforeach; ?>
            <?php if ($mobile): ?>
                <li class="primary-nav__logout"><?php renderLogoutControl('nav-link nav-link--button'); ?></li>
            <?php endif; ?>
        </ul>
    </nav>
    <?php
}

function renderLogoutControl(string $className): void
{
    $logoutToken = csrfToken('logout');
    ?>
    <form class="logout-form" method="POST" action="<?php echo escapeHtml(VACCINATION_LOGIN_PATH); ?>">
        <input type="hidden" name="action" value="logout">
        <input type="hidden" name="csrf_token" value="<?php echo escapeHtml($logoutToken); ?>">
        <button class="<?php echo escapeHtml($className); ?>" type="submit">Deconnexion</button>
    </form>
    <?php
}

function renderPageEnd(): void
{
    $year = (new DateTimeImmutable('now'))->format('Y');
    $businessItems = vaccinationBusinessNavigationItems();
    $script = vaccinationAssetPath('js/app.js');
    ?>
</main>

<footer class="site-footer">
    <div class="site-footer__inner">
        <div class="site-footer__top">
            <section class="site-footer__brand" aria-label="Identite du projet">
                <img
                    class="site-footer__logo"
                    src="<?php echo escapeHtml(vaccinationAssetPath('branding/logo-full-dark.png')); ?>"
                    alt="Identite visuelle Gestion Vaccination"
                    width="88"
                    height="88"
                >
                <div class="site-footer__brand-copy">
                    <p class="site-footer__eyebrow">Prototype pedagogique local</p>
                    <h2 class="site-footer__title">Gestion Vaccination</h2>
                    <p>Interface locale de demonstration pour l'authentification, l'inscription citoyenne, la recherche de centre et le rapport d'activite existant.</p>
                </div>
            </section>

            <nav class="site-footer__column" aria-label="Application">
                <h2 class="site-footer__heading">Application</h2>
                <ul class="site-footer__links">
                    <?php foreach ($businessItems as $item): ?>
                        <li><a href="<?php echo escapeHtml($item['href']); ?>"><?php echo escapeHtml($item['label']); ?></a></li>
                    <?php endforeach; ?>
                    <li><a href="<?php echo escapeHtml(VACCINATION_PASSWORD_CHANGE_PATH); ?>">Mot de passe</a></li>
                </ul>
            </nav>

            <section class="site-footer__column" aria-label="Securite et statut">
                <h2 class="site-footer__heading">Securite et statut</h2>
                <ul class="site-footer__facts">
                    <li>Acces administrateur securise.</li>
                    <li>Donnees de demonstration uniquement.</li>
                    <li>Aucune affiliation officielle.</li>
                    <li>Application non clinique.</li>
                </ul>
            </section>
        </div>

        <div class="site-footer__bottom">
            <p>&copy; <?php echo escapeHtml($year); ?> Ayman Bounaouj</p>
            <p>Prototype pedagogique local. Utiliser uniquement des donnees de demonstration.</p>
        </div>
    </div>
</footer>
<script src="<?php echo escapeHtml($script); ?>" defer></script>
</div>
</body>
</html>
    <?php
}

/** @param list<string> $messages */
function renderFeedback(string $type, array $messages, ?string $id = null): void
{
    $allowedTypes = ['success', 'error', 'warning', 'info'];
    if (!in_array($type, $allowedTypes, true) || $messages === []) {
        return;
    }
    $role = $type === 'success' || $type === 'info' ? 'status' : 'alert';
    $live = $role === 'status' ? 'polite' : 'assertive';
    ?>
    <div
        class="feedback feedback--<?php echo escapeHtml($type); ?>"
        role="<?php echo $role; ?>"
        aria-live="<?php echo $live; ?>"
        <?php echo $id !== null ? 'id="' . escapeHtml($id) . '"' : ''; ?>
    >
        <span class="feedback__marker" aria-hidden="true"></span>
        <div>
            <?php if (count($messages) === 1): ?>
                <p><?php echo escapeHtml($messages[0]); ?></p>
            <?php else: ?>
                <p class="feedback__title">Veuillez vérifier les points suivants :</p>
                <ul>
                    <?php foreach ($messages as $message): ?>
                        <li><?php echo escapeHtml($message); ?></li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>
    </div>
    <?php
}

function renderRequiredIndicator(): void
{
    ?><span class="required-indicator" aria-hidden="true">*</span><span class="sr-only"> obligatoire</span><?php
}
