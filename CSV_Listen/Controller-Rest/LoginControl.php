<?php

class LoginControl {
    public static $user_type_admin = "type_admin";
    public static $user_type_dropship = "type_dropship";
    public static $user_type_ansicht_kom = "type_ansicht_kom";
    /**
     * True: If given $user_type logged in. Else: False. Logged in = $user_type present in $_SESSION.
     *
     */
    public static function isUserLoggedIn($user_type){
        //1. check if a user exists in session
        if(isset($_SESSION["username"]) && !empty($_SESSION["username"])) {
            //echo 'Set and not empty, and no undefined index error!';

            //2. If yes: check if user_type in session equals given user_type
            if(isset($_SESSION["user_type"]) && !empty($_SESSION["user_type"])) {
                if($_SESSION["user_type"] === $user_type){
                    return true;
                }else {return false;}

            }else {return false;}

        }else {return false;}

    }
    /**
     * Put $username, $pw, $user_type into Session
     *
     */
    public static function logThisUserIn($username, $pw, $user_type){
        $_SESSION["username"] = $username;
        $_SESSION["pw"] = $pw;
        $_SESSION["user_type"] = $user_type;
        return true;
    }

    /**
     * Get current user from Session OR "not logged in" string
     *
     */
    public static function showCurrentUser(){
        if(isset($_SESSION["username"]) && !empty($_SESSION["username"])) {
            return $_SESSION["username"];
        }else {return 'not logged in';}
    }
    /**
     * Destroy Session and all user infos -> logout all users
     *
     */
    public static function destroySession(){
        // remove all session variables
        session_unset();

        // destroy the session
        //session_destroy(); //makes weird
    }
}