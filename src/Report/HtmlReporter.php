<?php

declare(strict_types=1);

namespace Amoifr\PicklePantherBundle\Report;

/**
 * Collects step results during the run and renders a standalone HTML report.
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

        $success = \count(array_filter(self::$results, static fn (StepResult $r) => $r->success));
        $total = \count(self::$results);
        $fails = $total - $success;

        $html = '<!DOCTYPE html><html lang="fr"><head><meta charset="UTF-8"><title>Rapport E2E</title>';
        $html .= '<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">';
        $html .= '<style>'.self::css().'</style>';
        $html .= '</head><body><div class="container">';
        $html .= '<div class="page-header">';
        $html .= '<div class="page-header-main">';
        if (null !== ($logo = self::logoDataUri())) {
            $html .= '<img class="brand-logo" src="'.$logo.'" alt="'.self::BUNDLE_NAME.'">';
        }
        $html .= '<div>';
        $html .= '<h1><i class="fas fa-chart-bar"></i>Rapport des tests E2E</h1>';
        $html .= '<div class="subtitle"><i class="fas fa-info-circle"></i>'.self::BUNDLE_NAME.' — résultats détaillés des tests automatisés</div>';
        $html .= '<p style="margin: 0;"><i class="fas fa-clock"></i> <strong>Généré le:</strong> '.date('Y-m-d H:i:s').' (heure de Paris)</p>';
        $html .= '</div></div></div>';

        $html .= '<div class="summary"><div class="summary-stats">';
        $html .= '<div class="stat-card total"><i class="fas fa-list"></i><div><div style="font-size: 0.85rem; opacity: 0.8;">Total</div>'.$total.' étape(s)</div></div>';
        $html .= '<div class="stat-card success-stat"><i class="fas fa-check-circle"></i><div><div style="font-size: 0.85rem; opacity: 0.8;">Réussies</div>'.$success.'</div></div>';
        $html .= '<div class="stat-card fail-stat"><i class="fas fa-times-circle"></i><div><div style="font-size: 0.85rem; opacity: 0.8;">Échecs</div>'.$fails.'</div></div>';
        $html .= '</div></div>';

        foreach ($grouped as $scenarioFile => $scenarios) {
            $html .= '<div class="yaml-file">';
            $html .= '<div class="yaml-file-header"><i class="fas fa-file-code"></i>'.htmlspecialchars((string) $scenarioFile).'</div>';

            foreach ($scenarios as $scenarioName => $scenarioData) {
                $html .= '<div class="scenario"><div class="scenario-header">';
                $html .= '<div class="scenario-name"><i class="fas fa-play-circle"></i>'.htmlspecialchars((string) $scenarioName).'</div>';
                if (!empty($scenarioData['description'])) {
                    $html .= '<div class="scenario-description">'.htmlspecialchars($scenarioData['description']).'</div>';
                }

                if (!empty($scenarioData['browser']) || !empty($scenarioData['identity'])) {
                    $html .= '<div class="context-badges">';
                    if (!empty($scenarioData['browser'])) {
                        $browser = (string) $scenarioData['browser'];
                        $icon = 'mobile' === $browser ? 'fa-mobile-alt' : 'fa-desktop';
                        $html .= '<span class="context-badge navigateur-'.htmlspecialchars($browser).'"><i class="fas '.$icon.'"></i>'.ucfirst(htmlspecialchars($browser)).'</span>';
                    }
                    if (!empty($scenarioData['identity'])) {
                        $identity = (string) $scenarioData['identity'];
                        $icon = 'admin' === $identity ? 'fa-user-shield' : 'fa-user';
                        $html .= '<span class="context-badge identifie-'.htmlspecialchars($identity).'"><i class="fas '.$icon.'"></i>'.ucfirst(htmlspecialchars($identity)).'</span>';
                    }
                    $html .= '</div>';
                }
                $html .= '</div>';

                $html .= '<table class="step-table"><thead><tr>';
                $html .= '<th style="width: 40%"><i class="fas fa-cog"></i> Action</th>';
                $html .= '<th style="width: 15%"><i class="fas fa-flag"></i> Résultat</th>';
                $html .= '<th style="width: 20%"><i class="fas fa-clock"></i> Heure</th>';
                $html .= '<th style="width: 25%"><i class="fas fa-camera"></i> Capture</th>';
                $html .= '</tr></thead><tbody>';

                $stepNumber = 1;
                foreach ($scenarioData['steps'] as $r) {
                    /** @var StepResult $r */
                    $html .= self::renderRow($r, $stepNumber);
                    ++$stepNumber;
                }

                $html .= '</tbody></table></div>';
            }

            $html .= '</div>';
        }

        $html .= self::footer();
        $html .= '</div>'.self::script().'</body></html>';
        file_put_contents($file, $html);

        echo "\n📊 Rapport généré : $file\n";
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
            .yaml-file { background: white; margin-bottom: 2rem; border-radius: 8px; box-shadow: 0 0.125rem 0.25rem rgba(0,0,0,0.075); overflow: hidden; border-left: 4px solid #667eea; transition: all 0.3s ease; }
            .yaml-file:hover { transform: translateY(-2px); box-shadow: 0 0.5rem 1rem rgba(0,0,0,0.15); }
            .yaml-file-header { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 1.25rem 1.5rem; font-size: 1.25rem; font-weight: 600; display: flex; align-items: center; gap: 0.75rem; }
            .scenario { margin: 1.5rem; border-left: 3px solid #28a745; background: #f8f9fa; border-radius: 6px; overflow: hidden; transition: all 0.2s ease; }
            .scenario:hover { background: #fff; box-shadow: 0 0.25rem 0.5rem rgba(0,0,0,0.1); }
            .scenario-header { background: #e9ecef; padding: 1rem 1.25rem; border-bottom: 1px solid #dee2e6; }
            .scenario-name { font-size: 1.15rem; font-weight: 600; color: #333; display: flex; align-items: center; gap: 0.5rem; }
            .scenario-name i { color: #28a745; }
            .scenario-description { font-size: 0.9rem; color: #6c757d; font-style: italic; margin-top: 0.5rem; padding-left: 1.75rem; }
            .context-badges { display: flex; gap: 0.5rem; margin-top: 0.75rem; padding-left: 1.75rem; flex-wrap: wrap; }
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
