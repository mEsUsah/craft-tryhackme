<?php

namespace mesusah\crafttryhackme\services;

use Craft;
use craft\base\Component;
use mesusah\crafttryhackme\TryHackMe;
use mesusah\crafttryhackme\models\Country;
use mesusah\crafttryhackme\models\User;
use mesusah\crafttryhackme\models\UserScore;

class LeaderboardService extends Component
{
    private $webEndpoint = "https://tryhackme.com";
    private $assetsEndpoint = "https://assets.tryhackme.com";
    private $cacheDuration = 3600;
    
    // Public Methods
    // =========================================================================

    /**
     * This function can literally be anything you want, and you can have as many service
     * functions as you want
     *
     * From any other plugin file, call it like this:
     *
     *     TryHackMe::getInstance()->thm->getScoreboard($locations)
     *
     * @return mixed
     */

    public function getScoreBoardUpdate()
    {
        $cacheKey = md5("tryHackMeScoreboardUpdated");
        $updatedTime = Craft::$app->cache->get($cacheKey);
        if ($updatedTime != null ) {
            return $updatedTime;
        }
        return null;
    }

    /**
     * Get the scoreboard for a list of locations
     * @param array $locations
     * @param bool $cache
     * @return array
     */
    public function getScoreboards($locations, $cache=true)
    {
        $locationsString = implode(",", $locations);
        $cacheKey = md5("tryHackMeScoreboard_" . $locationsString);
        $cachedData = Craft::$app->cache->get($cacheKey);
        if (!$cache || $cachedData != null ) {
            return $cachedData;
        }
        
        $returnArray = [
            "updated" => date("c"),
            "contries" => array(),
            "scoreboard" => array()
        ];

        Craft::$app->cache->set(
            md5("tryHackMeScoreboardUpdated"), 
            $returnArray['updated'], 
            $this->cacheDuration
        );

        foreach ($locations as $location) {
            array_push($returnArray["contries"], $location);

            $ranks = $this->getScoreboard($location);
            foreach ($ranks as $rank){
                array_push($returnArray["scoreboard"], $rank);
            }
        }

        usort($returnArray["scoreboard"], function ($item1, $item2) {
            return $item2['points'] <=> $item1['points'];
        });
        
        Craft::$app->cache->set($cacheKey, $returnArray, $this->cacheDuration);
        return $returnArray;
    }

    /**
     * Get the scoreboard for a specific location
     * 
     * @param string $location
     * @param bool $cache
     * @return array
     */
    public function getScoreboard($location, $cache=true)
    {
        $cacheKey = md5("tryHackMeScoreboard_" . $location);
        $cachedData = Craft::$app->cache->get($cacheKey);
        if ($cache && $cachedData != null ) {
            return $cachedData;
        }
        
        $url = "{$this->webEndpoint}/api/v2/leaderboards/general?period=all&country={$location}";
        $_h = curl_init();
        curl_setopt($_h, CURLOPT_HEADER, false);
        curl_setopt($_h, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($_h, CURLOPT_HTTPGET, 1);
        curl_setopt($_h, CURLOPT_URL, $url );
        curl_setopt($_h, CURLOPT_DNS_USE_GLOBAL_CACHE, false );
        curl_setopt($_h, CURLOPT_DNS_CACHE_TIMEOUT, 2 );

        $result = json_decode(curl_exec($_h), true);
        $data = $result["data"]["users"] ?? [];
        Craft::$app->cache->set($cacheKey, $data, $this->cacheDuration);
        return $data;
    }

    /**
     * Import the scoreboard for a specific location to database.
     * Will update existing users and scores on current date.
     * 
     * @param Country $country
     * @return array
     */
    public function importLeaderboard(Country $country)
    {
        // get all users in scoreboard
        $ranks = $this->getScoreboard($country->handle, false);
        
        $usersCreated = 0;
        $usersUpdated = 0;
        $scoreCreated = 0;
        $scoreUpdated = 0;

        try {
            foreach ($ranks as $rank){
                // Create/update user in database
                $user = User::find()->where(['name' => $rank['username']])->one();
                if ($user == null) {
                    $user = new User();
                    $user->name = $rank['username'];
                    $user->avatar = $rank['avatar'];
                    $user->country_id = $country->id;
                    $user->save();
                    $usersCreated++;
                } else {
                    $user->avatar = $rank['avatar'];
                    $user->save();
                    $usersUpdated++;
                }

                // Create/update user_score in database
                $user_score = UserScore::find()->where([
                    'user_id' => $user->id,
                    'date' => date("Y-m-d")
                ])->one();
                if($user_score == null){
                    $user_score = new UserScore();
                    $user_score->user_id = $user->id;
                    $user_score->date = date("Y-m-d");
                    $user_score->score = $rank['points'];
                    $user_score->save();
                    $scoreCreated++;
                } else {
                    $user_score->score = $rank['points'];
                    $user_score->save();
                    $scoreUpdated++;
                }
            }
        } catch (\Throwable $th) {
            return [
                'error' => $th->getMessage(),
                'users' => [
                    'import' => $usersCreated, 
                    'update' => $usersUpdated
                ],
                'scores' => [
                    'created' => $scoreCreated,
                    'updated' => $scoreUpdated
                ]
            ];
        }

        return [
            'users' => [
                'import' => $usersCreated, 
                'update' => $usersUpdated
            ],
            'scores' => [
                'created' => $scoreCreated,
                'updated' => $scoreUpdated
            ]
        ];
    }

    private function getCountryTopScore($countryIds, $date)
    {
        $scores = UserScore::find()
            ->with("user")
            ->leftJoin('tryhackme_user', 'tryhackme_user.id = tryhackme_user_score.user_id')
            ->where(['date' => $date])
            ->andWhere(['tryhackme_user.country_id' => $countryIds])
            ->orderBy(['score' => SORT_DESC])
            ->limit(50)
            ->all();

        return $scores;
    }

    public function readLeaderboard(array $params)
    {
        // Check if params are valid
        if(!isset($params['countries']) or sizeof($params['countries']) == 0){
            return false;
        }

        $country_ids = [];
        foreach ($params['countries'] as $country) {
            array_push($country_ids, $country->id);
        }

        // Check if date is set, if not set to today
        if(!isset($params['date']) or $params['date'] == ""){
            $params['date'] = date("Y-m-d");
        }

        // Get top scores for the countries
        $scores = $this->getCountryTopScore($country_ids, $params['date']);

        // If no scores are found for today, import leaderboards
        if(sizeof($scores) == 0){
            foreach ($params['countries'] as $country) {
                $this->importLeaderboard($country);
            }
            $scores = $this->getCountryTopScore($country_ids, $params['date']);
        }
        

        return $scores;
    }
}
