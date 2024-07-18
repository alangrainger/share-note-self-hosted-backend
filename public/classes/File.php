<?php

class File extends Controller
{
    const WHITELIST = [
        // HTML
        'html', 'css',
        // Images
        'jpg', 'jpeg', 'png', 'webp', 'svg', 'gif',
        // Fonts
        'ttf', 'otf', 'woff', 'woff2'
    ];
    const HASH_LENGTH = 32;

    private \DB\SQL\Mapper $file;
    private string $filehash;
    private string $extension;
    private bool $initialised = false;

    function __construct()
    {
        parent::__construct();
        // All file calls need a valid user. Will die() if not authenticated.
        $this->checkValidUser();
    }

    function initFile(): void
    {
        if ($this->initialised) {
            // Already initialised
            return;
        }

        // Perform basic sanity check on the filename
        $file = explode('.', $this->getPost('filename'));
        if (count($file) !== 2) {
            $this->errorAndDie(400); // Bad request
        }

        // Filename must be alphanumeric and match the hash length
        $this->filehash = preg_replace("/[^a-z0-9]/", '', $file[0]);
        if (strlen($this->filehash) !== self::HASH_LENGTH) {
            $this->errorAndDie(400); // Bad request
        }

        // File extension must be in our whitelist
        $this->extension = strtolower($file[1]);
        if (!in_array($this->extension, self::WHITELIST)) {
            $this->errorAndDie(415); // Unsupported media type
        }

        // If the file already exists, check that the owner is the current user
        $this->file = new DB\SQL\Mapper($this->db, 'files');
        $this->file->load(array('filename=? AND filetype=?', $this->filehash, $this->extension));
        if ($this->file->valid() && $this->file->users_id !== $this->user->id) {
            $this->errorAndDie(403); // Forbidden
        }

        $this->initialised = true;
    }

    function createNote(): void
    {

    }

    private function saveFile($contents): void
    {
        $filename = $this->getFilePath();

        // Create subfolders based off the filename, to reduce the number of files in the root folder
        $subfolder = $this->getSubfolder($this->filehash);
        $folder = $this->f3->get('upload_folder') . '/' . $subfolder;
        if (!file_exists($folder)) {
            mkdir($folder);
        }
        file_put_contents($filename, $contents);

        // Update the database
        $date = $this->now();
        if (!$this->file->valid()) {
            // This is a new record
            $this->file->users_id = $this->user->id;
            $this->file->filename = $this->filehash;
            $this->file->filetype = strtolower($this->extension);
            $this->file->created = $date;
        }
        $this->file->encrypted = $this->getPost('encrypted');
        $this->file->updated = $date;
        $this->file->bytes = filesize($filename);
        $this->file->save();
    }

    function upload(): void
    {
        $this->initFile();

        // Process the incoming file
        $contents = $this->getPost('content');
        if ($this->getPost('encoding') === 'base64') {
            // Decode uploaded images
            $contents = base64_decode($contents);
        }

        // Save the file to disk and update the database
        $this->saveFile($contents);

        // Output JSON data to return to the plugin
        $this->success([
            'url' => $this->getUrl()
        ]);
    }

    function delete(): void
    {
        $this->initFile();
        if ($this->file->valid()) {
            // Delete the local file
            unlink($this->getFilePath());
            // Delete the record from the database
            $this->file->erase();
            $this->success();
        }
        $this->failure();
    }

    /**
     * Gets the sub-path to the file, excluding domain or local storage location.
     * Used by getFilePath() and getUrl()
     * @return string
     */
    function getSubPath(): string
    {
        return "/{$this->getSubfolder($this->filehash)}/$this->filehash.$this->extension";
    }

    /**
     * Get the full path to the file on disk
     * @return string
     */
    function getFilePath(): string
    {
        return $this->f3->get('upload_folder') . $this->getSubPath();
    }

    /**
     * Get the public URL of the file
     * @return string
     */
    function getUrl(): string
    {
        return $this->f3->get('file_url_base') . $this->getSubPath();
    }

    function checkFile($hash, $extension): object
    {
        $fileDb = new DB\SQL\Mapper($this->db, 'files');
        $fileDb->load(array('filename=? AND filetype=?', $hash, $extension));
        if ($fileDb->valid()) {
            return (object)[
                'success' => true,
                'url' => $url ?? $this->getUrl()
            ];
        } else {
            return (object)[
                'success' => false,
                'url' => null
            ];
        }
    }

    function checkFiles(): object
    {
        $result = [];

        // Check the incoming files
        foreach ($this->getPost('files') as $file) {
            $res = $this->checkFile($file->hash, $file->filetype);
            $file->url = $res->url;
        }

        // Get the info on the user's CSS (if exists)
        $css = new DB\SQL\Mapper($this->db, 'files');
        $css->load(array('filename=? AND filetype=?', $this->user->id, 'css'));

        return (object)[
            'success' => true,
            'files' => $result,
            'css' => !$css->valid() ? null : (object)[
                'url' => $this->getUrl(),
                'hash' => $css->hash
            ]
        ];
    }

    /**
     * Provide a hash and get the subfolder name in return.
     */
    function getSubfolder($hash): string
    {
        return substr($hash, 20, 2);
    }
}