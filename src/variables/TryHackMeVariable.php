<?php

namespace mesusah\crafttryhackme\variables;

use Craft;
use mesusah\crafttryhackme\TryHackMe;
use mesusah\crafttryhackme\models\Country;

class TryHackMeVariable
{
    
    /** 
     * This will return the configured username from the plugin settings
     * 
     * Call this variable from a template with
     * 
     *     {{ craft.tryhackme.userName }}
     * 
     * @param null
     * @return string
    */
    public function userName()
    {
        return TryHackMe::getInstance()->getSettings()->username;
    }

    /** 
     * This will return all data on the configured user
     * from the TryHackMe API
     * 
     * Call this variable from a template with
     * 
     *     {{ craft.tryhackme.userData }}
     * 
     * @param null
     * @return array
    */
    public function userData()
    {
        $username = TryHackMe::getInstance()->getSettings()->username;
        $country = TryHackMe::getInstance()->getSettings()->country;
        $contryName = Country::find()->where(['id' => $country])->one()->name;
        $data = TryHackMe::getInstance()->user->userData($username);
        $data['country'] = $contryName;
        return $data;
    }

    public function leaderboardCountry()
    {
        $country_id = TryHackMe::getInstance()->getSettings()->country;
        $country = Country::find()->where(['id' => $country_id])->one();
        return TryHackMe::getInstance()->leaderboard->getScoreboard($country->handle);
    }
}
