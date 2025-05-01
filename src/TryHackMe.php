<?php

namespace mesusah\crafttryhackme;

use Craft;
use yii\base\Event;
use craft\base\Model;
use craft\base\Plugin;
use craft\web\twig\variables\Cp;
use craft\web\twig\variables\CraftVariable;
use craft\events\RegisterCpNavItemsEvent;
use mesusah\crafttryhackme\models\Settings;
use mesusah\crafttryhackme\variables\TryHackMeVariable;
use mesusah\crafttryhackme\services\UserService;
use mesusah\crafttryhackme\services\LeaderboardService;

/**
 * TryHackMe plugin
 *
 * @method static TryHackMe getInstance()
 * @method Settings getSettings()
 * @author mEsUsah
 * @copyright mEsUsah
 * @license MIT
 */
class TryHackMe extends Plugin
{
    public string $schemaVersion = '1.0.1';
    public bool $hasCpSettings = true;

    public static function config(): array
    {
        return [
            'components' => [
                'user' => UserService::class,
                'leaderboard' => LeaderboardService::class,
            ],
        ];
    }

    public function init(): void
    {
        parent::init();

        // Defer most setup tasks until Craft is fully initialized
        Craft::$app->onInit(function() {
            $this->attachEventHandlers();
            // ...
        });

        Event::on(
            Cp::class,
            Cp::EVENT_REGISTER_CP_NAV_ITEMS,
            function(RegisterCpNavItemsEvent $event) {
                $event->navItems[] = [
                    'url' => 'tryhackme/dashboard',
                    'label' => 'TryHackMe',
                    'icon' => '@mesusah/crafttryhackme/icon.svg',
                ];
            }
        );

        // Register our variables
        Event::on(
            CraftVariable::class,
            CraftVariable::EVENT_INIT,
            function (Event $event) {
                /** @var CraftVariable $variable */
                $variable = $event->sender;
                $variable->set('tryhackme', TryHackMeVariable::class);
            }
        );
    }

    protected function createSettingsModel(): ?Model
    {
        return Craft::createObject(Settings::class);
    }

    protected function settingsHtml(): ?string
    {
        return Craft::$app->view->renderTemplate('tryhackme/_settings.twig', [
            'plugin' => $this,
            'settings' => $this->getSettings(),
        ]);
    }

    private function attachEventHandlers(): void
    {
        // Register event handlers here ...
        // (see https://craftcms.com/docs/4.x/extend/events.html to get started)
    }

    
}
