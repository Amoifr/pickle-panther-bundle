<?php

declare(strict_types=1);

namespace Amoifr\PicklePantherBundle\Sentence;

use Amoifr\PicklePantherBundle\Attribute\Sentence;
use Facebook\WebDriver\WebDriverBy;

/**
 * Generic, project-agnostic browser sentences: navigation, clicking, typing,
 * waiting and assertions.
 */
final class CommonSentences extends AbstractSentenceProvider
{
    #[Sentence('Visite la page avec l\'[url]', 'fr')]
    #[Sentence('Visit the page at [url]', 'en')]
    public function visit(string $url): void
    {
        $this->client()->request('GET', $url);
    }

    #[Sentence('Saisit [value] dans le champ [selector]', 'fr')]
    #[Sentence('Type [value] in field [selector]', 'en')]
    public function typeInField(string $selector, string $value): void
    {
        // Native typing (sendKeys) fires real keyboard events, more reliable than
        // forcing .value via JS for inputs driven by a Stimulus controller.
        $this->client()->waitForVisibility($selector, 10);
        $element = $this->client()->findElement(WebDriverBy::cssSelector($selector));
        $element->clear();
        $element->sendKeys($value);

        $actual = $this->client()->executeScript(
            "const e = document.querySelector('".addslashes($selector)."'); return e ? e.value : null;"
        );
        $this->testCase()->assertSame(
            $value,
            $actual,
            "Field '$selector' holds '".(is_string($actual) ? $actual : 'null')."' instead of '$value' after typing"
        );
    }

    #[Sentence('Clique réellement sur l\'élément [selector]', 'fr')]
    #[Sentence('Really click the element [selector]', 'en')]
    public function clickNative(string $selector): void
    {
        // Real WebDriver click (not a synthetic JS .click()): fires events
        // realistically, useful for Stimulus controllers that ignore a
        // programmatic click().
        $this->client()->waitForVisibility($selector, 10);
        $this->client()->executeScript(
            "const e = document.querySelector('".addslashes($selector)."'); if (e) e.scrollIntoView({block: 'center'});"
        );
        $this->client()->wait(1);
        $this->client()->findElement(WebDriverBy::cssSelector($selector))->click();
        $this->client()->wait(1);
    }

    #[Sentence('Clique sur l\'élément [selector] avec JavaScript', 'fr')]
    #[Sentence('Click the element [selector] with JavaScript', 'en')]
    public function clickOnWithJs(string $selector): void
    {
        try {
            $this->client()->waitFor($selector, 10);

            $this->client()->wait()->until(
                fn () => $this->client()->executeScript('return document.readyState === "complete";')
            );

            $this->client()->wait(1);

            $this->client()->executeScript("
                const element = document.querySelector('".addslashes($selector)."');
                if (element) {
                    element.scrollIntoView({ behavior: 'instant', block: 'center' });
                    element.click();
                } else {
                    throw new Error('Element not found: ".addslashes($selector)."');
                }
            ");

            $this->client()->wait(1);
        } catch (\Exception $e) {
            echo "⚠️ Error clicking '$selector': ".$e->getMessage()."\n";
            throw $e;
        }
    }

    #[Sentence('Clique sur le lien [selector]', 'fr')]
    #[Sentence('Click the link [selector]', 'en')]
    public function clickOnLink(string $selector): void
    {
        $this->client()->getCrawler()->filter($selector)->click();
    }

    #[Sentence('Attend que la page et ses scripts soient complètement chargés', 'fr')]
    #[Sentence('Wait for the page and its scripts to be fully loaded', 'en')]
    public function waitForPageLoad(): void
    {
        $this->client()->wait()->until(
            fn () => $this->client()->executeScript('return document.readyState === "complete";')
        );

        $this->client()->wait(1);
    }

    #[Sentence('Clique sur un lien d\'ancre [selector] et vérifie le défilement jusqu\'à [targetId]', 'fr')]
    #[Sentence('Click anchor link [selector] and verify scrolling to [targetId]', 'en')]
    public function clickAnchorLink(string $selector, string $targetId): void
    {
        $this->client()->waitFor($selector);

        $this->client()->executeScript(
            "document.querySelector('".addslashes($selector)."').click();"
        );
        $this->client()->wait(1);

        $this->client()->executeScript('window.scrollBy(0, -200);');

        $this->testCase()->assertSelectorIsVisible($targetId);
    }

    #[Sentence('Vérifie que le selecteur [selector] est visible', 'fr')]
    #[Sentence('Verify that selector [selector] is visible', 'en')]
    public function assertSelectorVisible(string $selector): void
    {
        $this->client()->waitFor($selector, 10);

        $isVisible = $this->client()->wait(10)->until(
            fn () => (bool) $this->client()->executeScript("
                const element = document.querySelector('".addslashes($selector)."');
                if (!element) return false;
                if (element.classList.contains('hidden')) return false;
                const style = window.getComputedStyle(element);
                if (style.display === 'none') return false;
                if (style.visibility === 'hidden') return false;
                if (style.opacity === '0') return false;
                return true;
            ")
        );

        $this->testCase()->assertTrue(
            $isVisible,
            "Element '$selector' is not visible after waiting 10 seconds"
        );
    }

    #[Sentence('Remplit le champ [selector] du formulaire avec [value]', 'fr')]
    #[Sentence('Fill the form field [selector] with [value]', 'en')]
    public function fillField(string $selector, string $value): void
    {
        $exists = $this->client()->getCrawler()->filter($selector)->count();
        if (0 === $exists) {
            $allInputs = $this->client()->executeScript("
                return Array.from(document.querySelectorAll('input, textarea, select'))
                    .map(el => ({ tag: el.tagName, id: el.id, name: el.name, type: el.type }));
            ");
            throw new \Exception("Field $selector does not exist in the DOM. Available fields: ".json_encode($allInputs));
        }

        $isVisible = $this->isSelectorVisible($selector);
        $this->client()->wait(1);

        if ($isVisible) {
            try {
                $this->client()->waitForVisibility($selector, 2);
            } catch (\Exception) {
                // Our JS check says it's visible; Panther's detection may disagree.
            }
        }

        $this->client()->executeScript("
            const input = document.querySelector('".addslashes($selector)."');
            if (input) {
                input.focus();
                input.value = '".addslashes($value)."';
                input.dispatchEvent(new Event('input', { bubbles: true }));
                input.dispatchEvent(new Event('change', { bubbles: true }));
                input.dispatchEvent(new Event('blur', { bubbles: true }));
            }
        ");

        $this->client()->wait(1);
    }

    #[Sentence('Clique sur le bouton [selector]', 'fr')]
    #[Sentence('Click the button [selector]', 'en')]
    public function clickButton(string $selector): void
    {
        $this->client()->waitForVisibility($selector, 10);

        $this->client()->executeScript("
            const btn = document.querySelector('".addslashes($selector)."');
            if (btn) { btn.scrollIntoView({ behavior: 'smooth', block: 'center' }); }
        ");

        $this->client()->wait(1);
        $this->client()->getCrawler()->filter($selector)->click();
        $this->client()->wait(1);
    }

    #[Sentence('Vérifie que la modale [selector] est visible', 'fr')]
    #[Sentence('Verify that modal [selector] is visible', 'en')]
    public function assertModalIsVisible(string $selector): void
    {
        $this->client()->waitForVisibility($selector);
        $this->client()->waitFor($selector, 10);
        $this->testCase()->assertSelectorIsVisible($selector);
    }

    #[Sentence('Soumet le formulaire [selector]', 'fr')]
    #[Sentence('Submit the form [selector]', 'en')]
    public function submitForm(string $selector): void
    {
        $this->client()->executeScript("document.querySelector('".addslashes($selector)."').submit();");
    }

    #[Sentence('Attendre [time] secondes', 'fr')]
    #[Sentence('Wait [time] seconds', 'en')]
    public function waitForSeconds(string $time): void
    {
        $this->client()->wait((int) $time);
    }

    #[Sentence('Vérifie que le texte [text] est présent dans le sélecteur [selector]', 'fr')]
    #[Sentence('Verify that text [text] is present in selector [selector]', 'en')]
    public function assertTextInSelector(string $selector, string $text): void
    {
        $this->client()->waitFor($selector, 10);
        $this->testCase()->assertSelectorTextContains($selector, $text);
    }

    #[Sentence('Vérifie que le texte [text] n\'est pas présent dans le sélecteur [selector]', 'fr')]
    #[Sentence('Verify that text [text] is not present in selector [selector]', 'en')]
    public function assertTextNotInSelector(string $selector, string $text): void
    {
        $content = $this->client()->executeScript(
            "const el = document.querySelector('".addslashes($selector)."'); return el ? el.textContent : '';"
        );
        $this->testCase()->assertStringNotContainsString($text, is_string($content) ? $content : '');
    }

    #[Sentence('Vérifie que l\'URL contient [fragment]', 'fr')]
    #[Sentence('Verify that the URL contains [fragment]', 'en')]
    public function assertUrlContains(string $fragment): void
    {
        $this->testCase()->assertStringContainsString($fragment, $this->client()->getCurrentURL());
    }

    #[Sentence('Vérifie que l\'URL ne contient pas [fragment]', 'fr')]
    #[Sentence('Verify that the URL does not contain [fragment]', 'en')]
    public function assertUrlNotContains(string $fragment): void
    {
        $this->testCase()->assertStringNotContainsString($fragment, $this->client()->getCurrentURL());
    }

    #[Sentence('Vérifie que le selecteur [selector] existe', 'fr')]
    #[Sentence('Verify that selector [selector] exists', 'en')]
    public function assertSelectorExists(string $selector): void
    {
        $this->client()->waitFor($selector, 10);
        $this->testCase()->assertSelectorExists($selector);
    }

    #[Sentence('Vérifie qu\'au moins un élément [selector] est présent', 'fr')]
    #[Sentence('Verify that at least one element [selector] is present', 'en')]
    public function assertAtLeastOneElement(string $selector): void
    {
        $this->client()->waitFor($selector, 10);
        $count = $this->client()->getCrawler()->filter($selector)->count();
        $this->testCase()->assertGreaterThan(
            0,
            $count,
            "No element '$selector' was found on the page"
        );
    }

    #[Sentence('Vérifie qu\'aucun élément [selector] n\'est présent', 'fr')]
    #[Sentence('Verify that no element [selector] is present', 'en')]
    public function assertNoElement(string $selector): void
    {
        // Give a possible AJAX reload time to clear the area.
        try {
            $this->client()->wait(10)->until(
                fn () => 0 === $this->client()->getCrawler()->filter($selector)->count()
            );
        } catch (\Throwable) {
            // Timeout: the assertion below reports the real count.
        }

        $this->testCase()->assertSame(
            0,
            $this->client()->getCrawler()->filter($selector)->count(),
            "At least one element '$selector' is still present while none was expected"
        );
    }

    #[Sentence('Vérifie que le selecteur [selector] n\'est pas visible', 'fr')]
    #[Sentence('Verify that selector [selector] is not visible', 'en')]
    public function assertNotVisible(string $selector): void
    {
        // The element may stay in the DOM but hidden (e.g. closed pop-in):
        // wait for it to become invisible (or absent).
        $this->client()->waitForInvisibility($selector, 10);

        $visible = array_filter(
            $this->client()->findElements(WebDriverBy::cssSelector($selector)),
            fn ($element) => $element->isDisplayed()
        );
        $this->testCase()->assertCount(
            0,
            $visible,
            "Element '$selector' is visible while it should not be"
        );
    }
}
