<?php

declare(strict_types=1);

namespace Amoifr\PicklePantherBundle\Report;

/**
 * Collects step results during the run and renders a multi-page HTML report:
 * a home page ({@see generateReport}'s target file) listing every scenario as a
 * link, and one dedicated page per scenario (with a breadcrumb back home).
 *
 * Results are accumulated in a static buffer because the PHPUnit extension that
 * flushes the report ({@see HtmlReportExtension}) runs in a different object
 * (and outside the service container) than the reporter used during tests.
 */
final class HtmlReporter implements ReporterInterface
{
    private const BUNDLE_NAME = 'PicklePantherBundle';
    private const AUTHOR = 'Pascal CESCON (@Amoifr)';
    private const AUTHOR_EMAIL = 'pascal.cescon@gmail.com';

    /** @var list<StepResult> */
    private static array $results = [];

    public function addStep(StepResult $result): void
    {
        self::$results[] = $result;
    }

    public static function record(StepResult $result): void
    {
        self::$results[] = $result;
    }

    public static function reset(): void
    {
        self::$results = [];
    }

    public static function generateReport(string $file): void
    {
        date_default_timezone_set('Europe/Paris');

        $dir = \dirname($file);
        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }

        $indexName = basename($file);
        $baseName = pathinfo($file, \PATHINFO_FILENAME);

        // Group by scenario file then by scenario name.
        $grouped = [];
        foreach (self::$results as $r) {
            $scenarioFile = $r->scenarioFile ?? 'Non spécifié';
            $scenarioName = $r->scenarioName ?? 'Non spécifié';

            $grouped[$scenarioFile][$scenarioName] ??= [
                'description' => $r->scenarioDescription ?? '',
                'browser' => $r->browser,
                'identity' => $r->identity,
                'steps' => [],
            ];
            $grouped[$scenarioFile][$scenarioName]['steps'][] = $r;
        }

        // One entry per YAML file, with its own page and aggregated counters.
        $files = [];
        $index = 0;
        foreach ($grouped as $scenarioFile => $scenariosInFile) {
            ++$index;
            $total = 0;
            $pass = 0;
            foreach ($scenariosInFile as $data) {
                foreach ($data['steps'] as $r) {
                    ++$total;
                    if ($r->success) {
                        ++$pass;
                    }
                }
            }
            $files[] = [
                'file' => (string) $scenarioFile,
                'scenarios' => $scenariosInFile,
                'page' => $baseName.'-'.$index.'.html',
                'pass' => $pass,
                'fail' => $total - $pass,
                'total' => $total,
                'scenarioCount' => \count($scenariosInFile),
            ];
        }

        $generatedAt = date('Y-m-d H:i:s');

        // One page per YAML file (containing all its scenarios).
        foreach ($files as $f) {
            file_put_contents(
                $dir.'/'.$f['page'],
                self::renderFilePage($f, $indexName, $generatedAt),
            );
        }

        // Home page.
        $total = \count(self::$results);
        $success = \count(array_filter(self::$results, static fn (StepResult $r) => $r->success));
        file_put_contents($file, self::renderIndexPage($files, $total, $success, $total - $success, $generatedAt));

        echo "\n📊 Rapport généré : $file (".\count($files)." fichier(s))\n";
    }

    /**
     * @param list<array{file: string, scenarios: array<string, array{description: string, browser: ?string, identity: ?string, steps: list<StepResult>}>, page: string, pass: int, fail: int, total: int, scenarioCount: int}> $files
     */
    private static function renderIndexPage(array $files, int $total, int $success, int $fails, string $generatedAt): string
    {
        $body = self::pageHeader(
            '<i class="fas fa-chart-bar"></i>Rapport des tests E2E',
            self::BUNDLE_NAME.' — résultats détaillés des tests automatisés',
            $generatedAt,
        );

        $scenarioCount = array_sum(array_map(static fn (array $f): int => $f['scenarioCount'], $files));

        $body .= '<div class="summary"><div class="summary-stats">';
        $body .= '<div class="stat-card total"><i class="fas fa-list"></i><div><div class="stat-label">Total</div>'.$total.' étape(s)</div></div>';
        $body .= '<div class="stat-card success-stat"><i class="fas fa-check-circle"></i><div><div class="stat-label">Réussies</div>'.$success.'</div></div>';
        $body .= '<div class="stat-card fail-stat"><i class="fas fa-times-circle"></i><div><div class="stat-label">Échecs</div>'.$fails.'</div></div>';
        $body .= '<div class="stat-card scenario-stat"><i class="fas fa-play-circle"></i><div><div class="stat-label">Scénarios</div>'.$scenarioCount.'</div></div>';
        $body .= '</div></div>';

        // One link per YAML file, leading to its dedicated page.
        $body .= '<div class="yaml-file"><div class="yaml-file-header"><i class="fas fa-folder-open"></i> Fichiers de scénarios</div>';
        $body .= '<div class="scenario-links">';
        foreach ($files as $f) {
            $ok = 0 === $f['fail'];
            $statusClass = $ok ? 'success' : 'fail';
            $statusIcon = $ok ? 'fa-check-circle' : 'fa-times-circle';

            $body .= '<a class="scenario-link" href="'.htmlspecialchars($f['page']).'">';
            $body .= '<span class="scenario-link-status '.$statusClass.'"><i class="fas '.$statusIcon.'"></i></span>';
            $body .= '<span class="scenario-link-body">';
            $body .= '<span class="scenario-link-name"><i class="fas fa-file-code"></i> '.htmlspecialchars($f['file']).'</span>';
            $body .= '</span>';
            $body .= '<span class="scenario-link-stats">'.$f['scenarioCount'].' scénario(s) · '.$f['pass'].'/'.$f['total'].' OK</span>';
            $body .= '<span class="scenario-link-arrow"><i class="fas fa-chevron-right"></i></span>';
            $body .= '</a>';
        }
        $body .= '</div></div>';

        return self::page('Rapport E2E', $body);
    }

    /**
     * @param array{file: string, scenarios: array<string, array{description: string, browser: ?string, identity: ?string, steps: list<StepResult>}>, page: string, pass: int, fail: int, total: int, scenarioCount: int} $f
     */
    private static function renderFilePage(array $f, string $indexName, string $generatedAt): string
    {
        $body = '<nav class="breadcrumb">';
        $body .= '<a href="'.htmlspecialchars($indexName).'"><i class="fas fa-home"></i> Accueil</a>';
        $body .= '<i class="fas fa-chevron-right breadcrumb-sep"></i>';
        $body .= '<span class="breadcrumb-current">'.htmlspecialchars($f['file']).'</span>';
        $body .= '</nav>';

        $body .= self::pageHeader(
            '<i class="fas fa-file-code"></i>'.htmlspecialchars($f['file']),
            $f['scenarioCount'].' scénario(s) · '.$f['pass'].'/'.$f['total'].' étape(s) réussie(s)',
            $generatedAt,
        );

        foreach ($f['scenarios'] as $scenarioName => $data) {
            $body .= self::renderScenarioBlock((string) $scenarioName, $data);
        }

        $body .= '<div class="back-home"><a href="'.htmlspecialchars($indexName).'"><i class="fas fa-arrow-left"></i> Retour à l\'accueil</a></div>';

        return self::page($f['file'].' — Rapport E2E', $body);
    }

    /**
     * @param array{description: string, browser: ?string, identity: ?string, steps: list<StepResult>} $data
     */
    private static function renderScenarioBlock(string $scenarioName, array $data): string
    {
        $html = '<div class="scenario"><div class="scenario-header">';
        $html .= '<div class="scenario-name"><i class="fas fa-play-circle"></i>'.htmlspecialchars($scenarioName).'</div>';
        if ('' !== $data['description']) {
            $html .= '<div class="scenario-description">'.htmlspecialchars($data['description']).'</div>';
        }
        $html .= self::contextBadges($data['browser'], $data['identity']);
        $html .= '</div>';

        $html .= '<table class="step-table"><thead><tr>';
        $html .= '<th style="width: 40%"><i class="fas fa-cog"></i> Action</th>';
        $html .= '<th style="width: 15%"><i class="fas fa-flag"></i> Résultat</th>';
        $html .= '<th style="width: 20%"><i class="fas fa-clock"></i> Heure</th>';
        $html .= '<th style="width: 25%"><i class="fas fa-camera"></i> Capture</th>';
        $html .= '</tr></thead><tbody>';

        $stepNumber = 1;
        foreach ($data['steps'] as $r) {
            $html .= self::renderRow($r, $stepNumber);
            ++$stepNumber;
        }
        $html .= '</tbody></table></div>';

        return $html;
    }

    private static function page(string $title, string $bodyInner): string
    {
        $html = '<!DOCTYPE html><html lang="fr"><head><meta charset="UTF-8"><title>'.htmlspecialchars($title).'</title>';
        $html .= '<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">';
        $html .= '<style>'.self::css().'</style>';
        $html .= '</head><body><div class="container">';
        $html .= $bodyInner;
        $html .= self::footer();
        $html .= '</div>'.self::script().'</body></html>';

        return $html;
    }

    private static function pageHeader(string $titleHtml, string $subtitle, string $generatedAt): string
    {
        $html = '<div class="page-header"><div class="page-header-main">';
        if (null !== ($logo = self::logoDataUri())) {
            $html .= '<img class="brand-logo" src="'.$logo.'" alt="'.self::BUNDLE_NAME.'">';
        }
        $html .= '<div>';
        $html .= '<h1>'.$titleHtml.'</h1>';
        $html .= '<div class="subtitle"><i class="fas fa-info-circle"></i>'.$subtitle.'</div>';
        $html .= '<p style="margin: 0;"><i class="fas fa-clock"></i> <strong>Généré le:</strong> '.$generatedAt.' (heure de Paris)</p>';
        $html .= '</div></div></div>';

        return $html;
    }

    private static function contextBadges(?string $browser, ?string $identity): string
    {
        if (empty($browser) && empty($identity)) {
            return '';
        }

        $html = '<div class="context-badges">';
        if (!empty($browser)) {
            $icon = 'mobile' === $browser ? 'fa-mobile-alt' : 'fa-desktop';
            $html .= '<span class="context-badge navigateur-'.htmlspecialchars($browser).'"><i class="fas '.$icon.'"></i>'.ucfirst(htmlspecialchars($browser)).'</span>';
        }
        if (!empty($identity)) {
            $icon = 'admin' === $identity ? 'fa-user-shield' : 'fa-user';
            $html .= '<span class="context-badge identifie-'.htmlspecialchars($identity).'"><i class="fas '.$icon.'"></i>'.ucfirst(htmlspecialchars($identity)).'</span>';
        }
        $html .= '</div>';

        return $html;
    }

    private static function renderRow(StepResult $r, int $stepNumber): string
    {
        $statusClass = $r->success ? 'success' : 'fail';
        $statusIcon = $r->success ? 'fa-check-circle' : 'fa-times-circle';
        $statusText = $r->success ? 'OK' : 'FAIL';

        // Highlight argument values inside the action sentence, whether they are
        // referenced by placeholder name ("... [selector] ...") or written inline
        // ("... [#go-page2] ...").
        $actionTextWithArgs = htmlspecialchars($r->action);
        foreach ($r->args as $key => $value) {
            $highlighted = '<span class="arg-value">"'.htmlspecialchars($value).'"</span>';
            $actionTextWithArgs = str_replace(
                ['['.$key.']', '['.htmlspecialchars($value).']'],
                $highlighted,
                $actionTextWithArgs,
            );
        }

        $testDisplay = '<div class="action-name"><span class="step-number">'.$stepNumber.'</span>';
        if (!empty($r->title)) {
            $testDisplay .= '<div><div style="display: flex; align-items: center; gap: 0.5rem;">';
            $testDisplay .= '<i class="fas fa-bolt"></i> <span>'.htmlspecialchars($r->title).'</span>';
            $testDisplay .= '<button class="toggle-action-detail" title="Afficher/masquer l\'action"><i class="fas fa-chevron-down"></i></button></div>';
            $testDisplay .= '<div class="action-detail" style="display: none; margin-top: 0.5rem; padding-left: 1.5rem; font-size: 0.9rem; color: #6c757d;">';
            $testDisplay .= '<i class="fas fa-info-circle"></i> '.$actionTextWithArgs.'</div></div>';
        } else {
            $testDisplay .= '<div><i class="fas fa-bolt"></i> '.$actionTextWithArgs.'</div>';
        }
        $testDisplay .= '</div>';

        $statusDisplay = '<span class="status-badge '.$statusClass.'"><i class="fas '.$statusIcon.'"></i>'.$statusText.'</span>';
        $timestampDisplay = '<div class="timestamp"><i class="fas fa-clock"></i>'.date('Y-m-d H:i:s').'</div>';

        $screenshot = '<div class="screenshot-container">';
        if ($r->screenshot && file_exists($r->screenshot)) {
            // Captures live next to the report under captures/.
            $src = 'captures/'.rawurlencode(basename($r->screenshot));
            $screenshot .= '<a href="'.$src.'" target="_blank"><img src="'.$src.'" alt="Screenshot" title="Cliquez pour agrandir"></a>';
        } else {
            $screenshot .= '<span class="no-screenshot"><i class="fas fa-ban"></i> Aucune</span>';
        }
        $screenshot .= '</div>';

        return '<tr><td>'.$testDisplay.'</td><td>'.$statusDisplay.'</td><td>'.$timestampDisplay.'</td><td>'.$screenshot.'</td></tr>';
    }

    private static function footer(): string
    {
        $logo = self::logoDataUri();
        $email = self::AUTHOR_EMAIL;

        $html = '<footer class="report-footer">';
        if (null !== $logo) {
            $html .= '<img class="footer-logo" src="'.$logo.'" alt="'.self::BUNDLE_NAME.'">';
        }
        $html .= '<div>';
        $html .= '<div class="footer-name"><strong>'.self::BUNDLE_NAME.'</strong></div>';
        $html .= '<div class="footer-author">'.self::AUTHOR.' · <a href="mailto:'.$email.'">'.$email.'</a></div>';
        $html .= '</div>';
        $html .= '</footer>';

        return $html;
    }

    private static function logoDataUri(): ?string
    {
        $path = __DIR__.'/../../assets/logo-report.png';
        if (!is_file($path)) {
            return null;
        }
        $data = file_get_contents($path);
        if (false === $data) {
            return null;
        }

        return 'data:image/png;base64,'.base64_encode($data);
    }

    private static function css(): string
    {
        return <<<'CSS'
            * { margin: 0; padding: 0; box-sizing: border-box; }
            body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif; background-color: #f8f9fa; color: #212529; line-height: 1.6; }
            .container { max-width: 1400px; margin: 0 auto; padding: 2rem; }
            .page-header { background: white; padding: 2rem; border-radius: 8px; box-shadow: 0 0.125rem 0.25rem rgba(0,0,0,0.075); margin-bottom: 2rem; }
            .page-header h1 { color: #333; font-size: 2rem; margin-bottom: 0.5rem; display: flex; align-items: center; gap: 0.75rem; }
            .page-header h1 i { color: #667eea; }
            .page-header .subtitle { color: #6c757d; font-size: 0.95rem; margin-bottom: 0.75rem; display: flex; align-items: center; gap: 0.5rem; }
            .page-header-main { display: flex; align-items: center; gap: 1.5rem; }
            .brand-logo { width: 96px; height: 96px; flex-shrink: 0; }
            .stat-label { font-size: 0.85rem; opacity: 0.8; }
            .breadcrumb { background: white; padding: 0.85rem 1.25rem; border-radius: 8px; box-shadow: 0 0.125rem 0.25rem rgba(0,0,0,0.075); margin-bottom: 1.25rem; display: flex; align-items: center; gap: 0.6rem; font-size: 0.95rem; }
            .breadcrumb a { color: #667eea; text-decoration: none; font-weight: 600; display: inline-flex; align-items: center; gap: 0.4rem; }
            .breadcrumb a:hover { text-decoration: underline; }
            .breadcrumb-sep { color: #adb5bd; font-size: 0.7rem; }
            .breadcrumb-current { color: #6c757d; }
            .scenario-links { padding: 1rem 1.5rem 1.5rem; display: flex; flex-direction: column; gap: 0.75rem; }
            .scenario-link { display: flex; align-items: center; gap: 1rem; padding: 1rem 1.25rem; background: #f8f9fa; border: 1px solid #e9ecef; border-left: 4px solid #28a745; border-radius: 6px; text-decoration: none; color: inherit; transition: all 0.2s ease; }
            .scenario-link:hover { background: #fff; transform: translateX(4px); box-shadow: 0 0.25rem 0.5rem rgba(0,0,0,0.1); }
            .scenario-link-status { font-size: 1.4rem; flex-shrink: 0; }
            .scenario-link-status.success { color: #28a745; }
            .scenario-link-status.fail { color: #dc3545; }
            .scenario-link-body { flex: 1; min-width: 0; }
            .scenario-link-name { font-size: 1.1rem; font-weight: 600; color: #333; display: block; }
            .scenario-link-stats { flex-shrink: 0; font-size: 0.9rem; font-weight: 600; color: #495057; background: #e9ecef; padding: 0.35rem 0.75rem; border-radius: 999px; }
            .scenario-link-arrow { color: #adb5bd; flex-shrink: 0; }
            .back-home { margin: 1.5rem 0; }
            .back-home a { color: #667eea; text-decoration: none; font-weight: 600; display: inline-flex; align-items: center; gap: 0.5rem; }
            .back-home a:hover { text-decoration: underline; }
            .report-footer { margin-top: 2rem; padding: 1.5rem 2rem; background: white; border-radius: 8px; box-shadow: 0 0.125rem 0.25rem rgba(0,0,0,0.075); border-left: 4px solid #4f7d2e; display: flex; align-items: center; gap: 1rem; }
            .footer-logo { width: 56px; height: 56px; flex-shrink: 0; }
            .footer-name { color: #333; font-size: 1.05rem; }
            .footer-author { color: #6c757d; font-size: 0.9rem; margin-top: 0.15rem; }
            .footer-author a { color: #4f7d2e; text-decoration: none; }
            .footer-author a:hover { text-decoration: underline; }
            .summary { background: white; padding: 1.5rem; border-radius: 8px; box-shadow: 0 0.125rem 0.25rem rgba(0,0,0,0.075); margin-bottom: 2rem; }
            .summary-stats { display: flex; gap: 1rem; margin-top: 1rem; flex-wrap: wrap; }
            .stat-card { flex: 1; min-width: 200px; padding: 1rem 1.5rem; border-radius: 6px; font-size: 1.1rem; font-weight: 500; display: flex; align-items: center; gap: 0.75rem; }
            .stat-card i { font-size: 1.5rem; }
            .stat-card.total { background: #e7f1ff; color: #004085; }
            .stat-card.success-stat { background: #d4edda; color: #155724; }
            .stat-card.fail-stat { background: #f8d7da; color: #721c24; }
            .stat-card.scenario-stat { background: #ede7f6; color: #4527a0; }
            .yaml-file { background: white; margin-bottom: 2rem; border-radius: 8px; box-shadow: 0 0.125rem 0.25rem rgba(0,0,0,0.075); overflow: hidden; border-left: 4px solid #667eea; transition: all 0.3s ease; }
            .yaml-file:hover { transform: translateY(-2px); box-shadow: 0 0.5rem 1rem rgba(0,0,0,0.15); }
            .yaml-file-header { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 1.25rem 1.5rem; font-size: 1.25rem; font-weight: 600; display: flex; align-items: center; gap: 0.75rem; }
            .scenario { margin: 1.5rem; border-left: 3px solid #28a745; background: #f8f9fa; border-radius: 6px; overflow: hidden; transition: all 0.2s ease; }
            .scenario:hover { background: #fff; box-shadow: 0 0.25rem 0.5rem rgba(0,0,0,0.1); }
            .scenario-header { background: #e9ecef; padding: 1rem 1.25rem; border-bottom: 1px solid #dee2e6; }
            .scenario-name { font-size: 1.15rem; font-weight: 600; color: #333; display: flex; align-items: center; gap: 0.5rem; }
            .scenario-name i { color: #28a745; }
            .scenario-description { font-size: 0.9rem; color: #6c757d; font-style: italic; margin-top: 0.5rem; }
            .context-badges { display: flex; gap: 0.5rem; margin-top: 0.75rem; flex-wrap: wrap; }
            .context-badge { display: inline-flex; align-items: center; gap: 0.35rem; padding: 0.35rem 0.75rem; border-radius: 4px; font-size: 0.8rem; font-weight: 500; background: #fff; border: 1px solid #dee2e6; }
            .context-badge i { font-size: 0.9rem; }
            .context-badge.navigateur-mobile { background: #fff3cd; border-color: #ffc107; color: #856404; }
            .context-badge.navigateur-desktop { background: #d1ecf1; border-color: #17a2b8; color: #0c5460; }
            .context-badge.identifie-admin { background: #f8d7da; border-color: #dc3545; color: #721c24; }
            .step-table { width: 100%; border-collapse: collapse; background: white; }
            .step-table thead { background: #495057; color: white; }
            .step-table th { padding: 0.875rem 1rem; text-align: left; font-weight: 600; font-size: 0.9rem; text-transform: uppercase; letter-spacing: 0.5px; }
            .step-table td { padding: 1rem; border-bottom: 1px solid #dee2e6; vertical-align: top; }
            .step-table tbody tr:hover { background-color: rgba(102, 126, 234, 0.05); }
            .step-number { display: inline-flex; align-items: center; justify-content: center; width: 32px; height: 32px; background: #667eea; color: white; border-radius: 50%; font-weight: 600; font-size: 0.875rem; margin-right: 0.75rem; flex-shrink: 0; }
            .action-name { font-weight: 500; color: #212529; display: flex; align-items: center; gap: 0.5rem; }
            .action-name i { color: #667eea; }
            .arg-value { display: inline; padding: 0.2rem 0.5rem; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; border-radius: 4px; font-weight: 600; font-family: "Courier New", monospace; font-size: 0.9rem; }
            .status-badge { display: inline-flex; align-items: center; gap: 0.5rem; padding: 0.5rem 1rem; border-radius: 6px; font-weight: 600; font-size: 0.9rem; }
            .status-badge.success { background: #d4edda; color: #155724; }
            .status-badge.fail { background: #f8d7da; color: #721c24; }
            .timestamp { color: #6c757d; font-size: 0.875rem; display: flex; align-items: center; gap: 0.5rem; }
            .timestamp i { color: #667eea; }
            .screenshot-container { text-align: center; }
            .screenshot-container img { max-width: 200px; border-radius: 6px; box-shadow: 0 0.25rem 0.5rem rgba(0,0,0,0.15); transition: transform 0.2s ease; cursor: pointer; }
            .screenshot-container img:hover { transform: scale(1.05); }
            .no-screenshot { color: #adb5bd; font-size: 0.875rem; }
            .toggle-action-detail { border: none; background: none; color: #667eea; cursor: pointer; transition: transform 0.2s ease; text-decoration: none !important; }
            .toggle-action-detail.expanded i { transform: rotate(180deg); }
            @media (max-width: 768px) { .container { padding: 1rem; } .summary-stats { flex-direction: column; } .stat-card { min-width: 100%; } }
            CSS;
    }

    private static function script(): string
    {
        return <<<'JS'
            <script>
            document.addEventListener("DOMContentLoaded", function() {
                document.querySelectorAll(".toggle-action-detail").forEach(button => {
                    button.addEventListener("click", function(e) {
                        e.preventDefault();
                        const actionDetail = this.closest("div").nextElementSibling;
                        if (actionDetail && actionDetail.classList.contains("action-detail")) {
                            const hidden = actionDetail.style.display === "none";
                            actionDetail.style.display = hidden ? "block" : "none";
                            this.classList.toggle("expanded", hidden);
                        }
                    });
                });
            });
            </script>
            JS;
    }
}