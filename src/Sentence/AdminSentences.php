<?php

declare(strict_types=1);

namespace Amoifr\PicklePantherBundle\Sentence;

use Amoifr\PicklePantherBundle\Attribute\Sentence;

/**
 * Reusable admin/back-office sentences working against common patterns
 * (a `#main-menu` navigation and `table` / `table.datagrid-table` datagrids,
 * as produced by EasyAdmin-style back-offices). Override or add your own
 * provider if your admin markup differs.
 */
final class AdminSentences extends AbstractSentenceProvider
{
    #[Sentence('Clique sur le lien du menu admin contenant le texte [text]', 'fr')]
    #[Sentence('Click the admin menu link containing the text [text]', 'en')]
    public function clickAdminMenuLink(string $text): void
    {
        $this->client()->waitFor('#main-menu', 10);

        $clicked = (bool) $this->client()->executeScript("
            const menuItems = document.querySelectorAll('#main-menu a, #main-menu button');
            let clicked = false;
            menuItems.forEach(item => {
                if (item.textContent.trim().includes('".addslashes($text)."')) {
                    item.click();
                    clicked = true;
                }
            });
            return clicked;
        ");

        $this->testCase()->assertTrue(
            $clicked,
            "Admin menu link containing '$text' was not found"
        );

        $this->client()->wait(1);
    }

    #[Sentence('Clique sur le lien du sous-menu admin contenant le texte [text]', 'fr')]
    #[Sentence('Click the admin submenu link containing the text [text]', 'en')]
    public function clickAdminSubmenuLink(string $text): void
    {
        $this->client()->wait(1);

        $clicked = (bool) $this->client()->executeScript("
            const submenuItems = document.querySelectorAll('#main-menu .submenu-item a, #main-menu li a');
            let clicked = false;
            submenuItems.forEach(item => {
                if (item.textContent.trim().includes('".addslashes($text)."')) {
                    item.click();
                    clicked = true;
                }
            });
            return clicked;
        ");

        $this->testCase()->assertTrue(
            $clicked,
            "Admin submenu link containing '$text' was not found"
        );

        $this->client()->wait(1);
    }

    #[Sentence('Attend que le tableau soit chargé', 'fr')]
    #[Sentence('Wait for the table to be loaded', 'en')]
    public function waitForTable(): void
    {
        $this->client()->waitFor('table.datagrid-table, table', 10);
    }

    #[Sentence('Vérifie que le tableau contient une ligne avec le texte [text]', 'fr')]
    #[Sentence('Verify that the table contains a row with the text [text]', 'en')]
    public function assertTableRowContainsText(string $text): void
    {
        $this->waitForTable();

        $found = (bool) $this->client()->executeScript("
            const tables = document.querySelectorAll('table.datagrid-table, table');
            let found = false;
            tables.forEach(table => {
                table.querySelectorAll('tbody tr').forEach(row => {
                    if (row.textContent.includes('".addslashes($text)."')) { found = true; }
                });
            });
            return found;
        ");

        $this->testCase()->assertTrue(
            $found,
            "No row found containing the text '$text'"
        );
    }

    #[Sentence('Vérifie que le tableau contient une ligne avec tous les textes [texts] (séparés par des virgules)', 'fr')]
    #[Sentence('Verify that the table contains a row with all the texts [texts] (comma-separated)', 'en')]
    public function assertTableRowContainsAllTexts(string $texts): void
    {
        $this->waitForTable();

        $textArray = array_map('trim', explode(',', $texts));
        $jsTextArray = json_encode($textArray);

        $found = (bool) $this->client()->executeScript("
            const searchTexts = {$jsTextArray};
            const tables = document.querySelectorAll('table.datagrid-table, table');
            let found = false;
            tables.forEach(table => {
                table.querySelectorAll('tbody tr').forEach(row => {
                    const rowText = row.textContent;
                    if (searchTexts.every(text => rowText.includes(text))) { found = true; }
                });
            });
            return found;
        ");

        $this->testCase()->assertTrue(
            $found,
            'No row found containing all the texts: '.implode(', ', $textArray)
        );
    }

    #[Sentence('Clique sur le bouton actions (3 points) de la ligne contenant [text]', 'fr')]
    #[Sentence('Click the actions button (3 dots) of the row containing [text]', 'en')]
    public function clickActionsButtonForRow(string $text): void
    {
        $this->waitForTable();

        $clicked = (bool) $this->client()->executeScript("
            const tables = document.querySelectorAll('table.datagrid-table, table');
            let clicked = false;
            tables.forEach(table => {
                table.querySelectorAll('tbody tr').forEach(row => {
                    if (row.textContent.includes('".addslashes($text)."')) {
                        const actionBtn = row.querySelector('.dropdown-toggle, [data-bs-toggle=\"dropdown\"], .actions-dropdown-toggle');
                        if (actionBtn) { actionBtn.click(); clicked = true; }
                    }
                });
            });
            return clicked;
        ");

        $this->testCase()->assertTrue(
            $clicked,
            "Actions button was not found for the row containing '$text'"
        );

        $this->client()->wait(1);
    }

    #[Sentence('Clique sur l\'action [actionText] dans le menu déroulant', 'fr')]
    #[Sentence('Click the action [actionText] in the dropdown menu', 'en')]
    public function clickDropdownAction(string $actionText): void
    {
        $this->client()->wait(1);

        $clicked = (bool) $this->client()->executeScript("
            const dropdownItems = document.querySelectorAll('.dropdown-menu a, .dropdown-menu button, [role=\"menu\"] a, [role=\"menu\"] button');
            let clicked = false;
            dropdownItems.forEach(item => {
                if (item.textContent.trim().includes('".addslashes($actionText)."')) {
                    item.click();
                    clicked = true;
                }
            });
            return clicked;
        ");

        $this->testCase()->assertTrue(
            $clicked,
            "Action '$actionText' was not found in the dropdown menu"
        );

        $this->client()->wait(1);
    }
}
