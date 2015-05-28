<?php
use \AcceptanceTester;

class AcceptCest
{
    public function _before(AcceptanceTester $I)
    {
    }

    public function _after(AcceptanceTester $I)
    {
    }

    /**
     * Test that the user can access the ACP (which means the prefixes in the database were properly replaced) and deactivate the plugin (which tests that the deactivation works fine and that all instances are destroyed).
     */
    public function loginToACP(AcceptanceTester $I)
    {
        $I->wantTo('log in to the admin control panel and deactivate the plugin');
        $I->amOnPage('/');
        $I->amOnPage('wp-login.php');
        $I->fillField('Username', 'root');
        $I->fillField('Password', 'pass');
        $I->click('#wp-submit');
        $I->see('Dashboard');

        $I->click('Plugins');
        $I->click('Deactivate');
        $I->see('Welcome to the famous five-minute WordPress installation process!');
    }

    /**
     * Check that the user can manually remove instances.
     */
    public function removeInstances(AcceptanceTester $I)
    {
        $I->wantTo('delete a demo instance');
        $I->amOnPage('wp-login.php');
        $I->fillField('Username', 'root');
        $I->fillField('Password', 'pass');
        $I->click('#wp-submit');
        $I->see('Dashboard');

        $I->click('Plugins');
        $I->click('Remove Instances (1)');
        $I->see('Welcome to the famous five-minute WordPress installation process!');
    }
}