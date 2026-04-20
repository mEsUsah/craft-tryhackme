<?php

namespace mesusah\crafttryhackme\services;

use Craft;
use craft\base\Component;
use mesusah\crafttryhackme\TryHackMe;

class UserService extends Component
{
    private $webEndpoint = "https://tryhackme.com";
    private $assetsEndpoint = "https://assets.tryhackme.com";
    private $cacheDuration = 3600;

    /**
     * Get the user rank from the TryHackMe API
     *
     * @param string $username
     * @return array
     */
     public function userData($username)
     {
        $cacheKey = md5("tryHackMeUserData_" . $username);
        $cachedData = Craft::$app->cache->get($cacheKey);
        if ($cachedData != null ) {
            return json_decode($cachedData, true);
        }

        $profile = $this->getUserProfile($username);

        $badgeDocs = $this->getBadgesUser($username);
        $badges = [];
        foreach ($badgeDocs as $badge) {
            $badges[$badge['name']] = [
                'title'       => $badge['title'],
                'name'        => $badge['name'],
                'description' => $badge['description'],
                'image'       => $badge['image'],
                'earned'      => true,
            ];
        }

        $data = [
            'userName'       => $username,
            'userRank'       => $profile['rank'] ?? null,
            'points'         => $profile['totalPoints'] ?? 0,
            'avatar'         => $profile['avatar'] ?? null,
            'country'        => $profile['country'] ?? null,
            'badges'         => $badges,
            'earnedBadges'   => $profile['badgesNumber'] ?? 0,
            'completedRooms' => $profile['completedRoomsNumber'] ?? 0,
        ];

        Craft::$app->cache->set($cacheKey, json_encode($data), $this->cacheDuration);

        return $data;
     }

    public function getUserProfile($username)
    {
        $url = "{$this->webEndpoint}/api/v2/public-profile?username={$username}";
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $output = curl_exec($ch);
        curl_close($ch);
        $json = json_decode($output, true);
        return $json['data'] ?? [];
    }

    public function getUserRank($username)
    {
        $url = "{$this->webEndpoint}/api/discord/user/{$username}";
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $output = curl_exec($ch);
        curl_close($ch);
        $json = json_decode($output, true);
        return $json;
    }

    /**
     * Get all badges from the TryHackMe API
     *
     * @param null
     * @return array
     */
    public function getBadgesAll()
    {
        $url = "{$this->webEndpoint}/api/badges/get";
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $output = curl_exec($ch);
        curl_close($ch);
        $json = json_decode($output, true);
        return $json;
    }

    /**
     * Get users badges from the TryHackMe API
     *
     * @param string $username
     * @return array
     */
    public function getBadgesUser($username)
    {
        $url = "{$this->webEndpoint}/api/v2/public-profile/badges?username={$username}&limit=100&page=1";
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $output = curl_exec($ch);
        curl_close($ch);
        $json = json_decode($output, true);
        return $json['data']['docs'] ?? [];
    }

    /**
     * Get users completed rooms from the TryHackMe API
     *
     * @param string $username
     * @return array
     */
    public function getCompletedRooms($username)
    {
        $url = "{$this->webEndpoint}/api/all-completed-rooms?username={$username}&limit=1000";
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $output = curl_exec($ch);
        curl_close($ch);
        $json = json_decode($output, true);
        return $json;
    }
}
