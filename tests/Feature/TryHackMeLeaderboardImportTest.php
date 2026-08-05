<?php
namespace mesusah\crafttryhackme\tests\Feature;

use Craft;
use PHPUnit\Framework\TestCase;
use yii\console\ExitCode;
use mesusah\crafttryhackme\TryHackMe;
use mesusah\crafttryhackme\models\Country;
use mesusah\crafttryhackme\services\LeaderboardService;

final class TryHackMeLeaderboardImportTest extends TestCase
{
    private $originalLeaderboardService;
    private $originalCountry;
    private $originalCountryCompetition;

    protected function setUp(): void
    {
        parent::setUp();

        $settings = TryHackMe::getInstance()->getSettings();
        $this->originalCountry = $settings->country;
        $this->originalCountryCompetition = $settings->countryCompetition;
        $this->originalLeaderboardService = TryHackMe::getInstance()->leaderboard;
    }

    protected function tearDown(): void
    {
        $settings = TryHackMe::getInstance()->getSettings();
        $settings->country = $this->originalCountry;
        $settings->countryCompetition = $this->originalCountryCompetition;
        TryHackMe::getInstance()->set('leaderboard', $this->originalLeaderboardService);

        parent::tearDown();
    }

    public function test_import_command_imports_leaderboard_for_home_country_and_competition(): void
    {
        $norway = Country::find()->where(['handle' => 'no'])->one();
        $usa = Country::find()->where(['handle' => 'us'])->one();

        $this->assertNotNull($norway, 'Expected seeded country "no" to exist.');
        $this->assertNotNull($usa, 'Expected seeded country "us" to exist.');

        // Home country is deliberately left out of the competition list, so the
        // command's logic of also importing the home country is also exercised.
        $settings = TryHackMe::getInstance()->getSettings();
        $settings->country = $norway->id;
        $settings->countryCompetition = [$usa->id];

        $fakeLeaderboardService = new class extends LeaderboardService {
            public array $importedCountryIds = [];

            public function importLeaderboard(Country $country)
            {
                $this->importedCountryIds[] = $country->id;

                return [
                    'users' => ['import' => 0, 'update' => 0],
                    'scores' => ['created' => 0, 'updated' => 0],
                ];
            }
        };
        TryHackMe::getInstance()->set('leaderboard', $fakeLeaderboardService);

        $exitCode = Craft::$app->runAction('tryhackme/leaderboard/import');

        $this->assertSame(ExitCode::OK, $exitCode);

        $importedCountryIds = $fakeLeaderboardService->importedCountryIds;
        sort($importedCountryIds);
        $this->assertSame([$norway->id, $usa->id], $importedCountryIds);
    }

    public function test_import_command_continues_and_exits_with_error_when_a_country_fails(): void
    {
        $norway = Country::find()->where(['handle' => 'no'])->one();
        $usa = Country::find()->where(['handle' => 'us'])->one();

        $this->assertNotNull($norway, 'Expected seeded country "no" to exist.');
        $this->assertNotNull($usa, 'Expected seeded country "us" to exist.');

        $settings = TryHackMe::getInstance()->getSettings();
        $settings->country = $norway->id;
        $settings->countryCompetition = [$usa->id];

        // Simulates the real-world failure this test suite caught: TryHackMe
        // returning a non-JSON response (e.g. a bot-protection page) for one
        // country. The command must report it and keep processing the rest,
        // rather than crash the whole run.
        $fakeLeaderboardService = new class extends LeaderboardService {
            public array $importedCountryIds = [];

            public function importLeaderboard(Country $country)
            {
                $this->importedCountryIds[] = $country->id;

                if ($country->handle === 'us') {
                    return ['error' => "Failed to fetch TryHackMe scoreboard for {$country->name}."];
                }

                return [
                    'users' => ['import' => 0, 'update' => 0],
                    'scores' => ['created' => 0, 'updated' => 0],
                ];
            }
        };
        TryHackMe::getInstance()->set('leaderboard', $fakeLeaderboardService);

        $exitCode = Craft::$app->runAction('tryhackme/leaderboard/import');

        $this->assertSame(ExitCode::UNSPECIFIED_ERROR, $exitCode);

        $importedCountryIds = $fakeLeaderboardService->importedCountryIds;
        sort($importedCountryIds);
        $this->assertSame([$norway->id, $usa->id], $importedCountryIds);
    }
}
