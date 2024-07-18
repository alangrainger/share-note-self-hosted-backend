<?php

class User extends Controller
{
    function getKey(): void
    {
        // Check the incoming UID value
        $uid = $this->getPost('id');
        if (!$uid || !is_string($uid) || !strlen($uid)) {
            $this->errorAndDie(400); // Bad request
        }

        // Check to see whether this user already exists
        $user = new DB\SQL\Mapper($this->db, 'users');
        $user->load(array('hash_id=?', $uid));
        if (!$user->valid()) {
            // This is a new user
            $user          = new DB\SQL\Mapper($this->db, 'users');
            $user->hash_id = $uid;
            $user->created = $this->now();
            if (!$user->save()) {
                // Failed to add user
                $this->errorAndDie(500); // Server error
            }
        }
        $this->user = $user;
        // Revoke any existing keys
        $this->db->exec(
            'UPDATE api_keys SET revoked=NOW() WHERE users_id = ? AND revoked IS NULL',
            $user->id
        );
        // Create the API key
        $apiKey           = new DB\SQL\Mapper($this->db, 'api_keys');
        $apiKey->users_id = $user->id;
        $apiKey->api_key  = $this->createApiKey($uid);
        $apiKey->created  = $this->now();
        if (!$apiKey->save()) {
            $this->errorAndDie(500); // Server error
        } else {
            $this->success([
                'id'  => $uid,
                'key' => $apiKey->api_key
            ]);
        }
    }

    function createApiKey($uid): string
    {
        return $this->hash($uid . microtime());
    }
}