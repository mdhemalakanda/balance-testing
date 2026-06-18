<?php
namespace BalanceTesting\API;

use BalanceTesting\SingletonTrait;
/**
 * This class will handle all api.
 */
class API {
    use SingletonTrait;

    public function init() {
        TestUsers::instance()->init();
        Ratings::instance()->init();
        RoundRules::instance()->init();
        UserExercises::instance()->init();
    }
}