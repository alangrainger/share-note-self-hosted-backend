<?php

class Controller
{
    protected \DB\SQL $db;
    protected Base $f3;
    protected object $json_post_data;
    protected \DB\SQL\Mapper $user;
    protected bool $isForm = false;
    protected bool $isJson = false;

    function __construct()
    {
        $this->f3 = Base::instance();
        $this->db = new DB\SQL(
            "mysql:host={$this->f3->get('db_host')};port=3306;dbname={$this->f3->get('db_name')}",
            $this->f3->get('db_user'),
            $this->f3->get('db_pass')
        );

        // Populate the post_data object depending on POST method
        if (str_contains($_SERVER['CONTENT_TYPE'], 'application/json')) {
            $this->isJson = true;
            try {
                $this->json_post_data = json_decode(file_get_contents("php://input"));
            } catch (Throwable) {
                $this->json_post_data = (object)[];
            }
        } else if (str_contains($_SERVER['CONTENT_TYPE'], 'multipart/form-data')) {
            $this->isForm = true;
        }
    }

    function getPost($property)
    {
        if ($this->isForm) {
            // FormData POST
            return $_POST[$property] ?? null;
        } else if ($this->isJson) {
            // JSON POST
            return $this->json_post_data->{$property} ?? null;
        }
        return null;
    }

    function hash($data): string
    {
        $sha = hash('sha256', $data);
        return substr($sha, 0, $this->f3->get('hash_length'));
    }

    function now(): string
    {
        return date('Y-m-d H:i:s');
    }

    /**
     * Checks for an authenticated user, and will die() if none found
     */
    function checkValidUser($statusCode = 401): void
    {
        $uid   = $this->getPost('id');
        $hash  = $this->getPost('key');
        $nonce = $this->getPost('nonce');

        if (!is_string($uid) || !is_string($hash)) {
            $this->errorAndDie($statusCode); // Unauthorised
        }
        $userDb = new DB\SQL\Mapper($this->db, 'users');
        $userDb->load(array('hash_id=?', $uid));
        if (!$userDb->valid()) {
            $this->errorAndDie($statusCode); // Unauthorised
        }
        $keyDb = new DB\SQL\Mapper($this->db, 'api_keys');
        $keyDb->load(array('users_id=? AND revoked IS NULL', $userDb->id));
        if (!$keyDb->valid()) {
            $this->errorAndDie($statusCode); // Unauthorised
        }

        // Check to see if the stored key matches the provided key
        if ($nonce) {
            // Authentication method as of v0.3.2
            $checkHash = $this->hash($nonce . $keyDb->api_key);
            $valid     = hash_equals($checkHash, $hash);
        } else {
            // Legacy auth method
            $valid = $hash === $keyDb->api_key;
        }

        if ($valid) {
            // Store the valid user for use in other functions
            $this->user = $userDb;
            if (!$keyDb->validated) {
                // Validate the key if this is the first time it has been used
                $keyDb->validated = $this->now();
                $keyDb->save();
            }
        } else {
            $this->errorAndDie($statusCode); // Unauthorised
        }
    }

    /**
     * Output an HTTP error and immediately die()
     * @param int $httpResponseCode
     * @return void
     */
    function errorAndDie(int $httpResponseCode): void
    {
        $log           = new DB\SQL\Mapper($this->db, 'logs');
        $log->endpoint = $this->f3->get('PATH');
        $log->version  = $this->getPost('version');
        $log->status   = $httpResponseCode;
        // Add the incoming data array for JSON requests
        if ($this->isJson) {
            $postData = $this->json_post_data;
            if (isset($postData->content)) {
                if (is_string($postData->content)) {
                    if (strlen($postData->content) > 200) {
                        $postData->content = substr($postData->content, 0, 197) . '...';
                    }
                } else {
                    $postData->content = null;
                }
            }
            $log->data = json_encode($postData);
        }
        if (!empty($this->user)) {
            $log->users_id = $this->user->id;
        }
        $log->save();

        $this->f3->error($httpResponseCode);
        die();
    }

    /**
     * Output a success JSON object for consumption by the frontend plugin
     * @param array $data
     * @return void
     */
    function success(array $data = []): void
    {
        $data['success'] = true;
        echo json_encode($data);
        exit();
    }

    function failure(): void
    {
        echo json_encode([
            'success' => false
        ]);
        die();
    }
}