<?php

class PostController extends BaseController {
    private $fileId = null;

    protected static function getView(): string {
        return '';
    }

    private function uploadFile() {
        $file = array_get_item('file', $_FILES);
        if (!is_array($file) || !array_key_exists('name', $file) || !array_key_exists('tmp_name', $file)) {
            return;
        }

        $orig_filename = basename($file['name']);
        $tmp_filename = $file['tmp_name'];
        if (empty($tmp_filename)) {
            return;
        }

        $hash = hash_file('sha1', $tmp_filename);
        $file_model = new FileModel();
        $file = $file_model->getByHash($hash);
        if ($file !== false) {
            // TODO: Redirect to post that has that image.
            throw new Exception('This image has already been uploaded.');
        }

        list($orig_width, $orig_height, $type) = @getimagesize($tmp_filename);
        if ($orig_width === null || $orig_height === null || $type === null) {
            throw new Exception('An error occured.');
        }

        if (in_array($type, ALLOWED_UPLOAD_FILE_TYPES, true) === false) {
            throw new Exception('File type not allowed.');
        }

        $file_size = ceil(@filesize($tmp_filename) / 1024);
        if ($file_size > MAX_UPLOAD_FILE_SIZE_KB) {
            throw new Exception('Files cannot be larger than ' . number_format(MAX_UPLOAD_FILE_SIZE_KB) . 'KB');
        }

        $thumbnail_width = 0;
        $thumbnail_height = 0;
        $aspect_ratio = $orig_width / $orig_height;
        if ($orig_height > $orig_width) {
            $thumbnail_height = THUMBNAIL_MAX_DIMENSION_SIZE;
            $thumbnail_width = $thumbnail_height * $aspect_ratio;
        }
        else {
            $thumbnail_width = THUMBNAIL_MAX_DIMENSION_SIZE;
            $thumbnail_height = $thumbnail_width / $aspect_ratio;
        }

        $image = null;
        $ext = null;
        switch ($type) {
            case IMAGETYPE_GIF:
                $ext = 'gif';
                $image = @imagecreatefromgif($tmp_filename);
                break;
            case IMAGETYPE_JPEG:
                $ext = 'jpg';
                $image = @imagecreatefromjpeg($tmp_filename);
                break;
            case IMAGETYPE_PNG:
                $ext = 'png';
                $image = @imagecreatefrompng($tmp_filename);
                break;
            default:
                throw new Exception('Invalid image type.');
        }

        if (!$image) {
            throw new Exception('There was an error with that image.');
        }

        $thumbnail = imagecreatetruecolor($thumbnail_width, $thumbnail_height);
        if ($thumbnail === false || !imagecopyresampled($thumbnail, $image, 0, 0, 0, 0, $thumbnail_width, $thumbnail_height, $orig_width, $orig_height)) {
            throw new Exception('There was an error resizing the image.');
        }

        $file_id = $file_model->insert($orig_filename, $file_size, $ext, $thumbnail_width, $thumbnail_height, $hash, time(), $_SERVER['REMOTE_ADDR']);

        if (!file_exists(DIR_IMAGES)) {
            if (!mkdir(DIR_IMAGES, 0777, true)) {
                throw new Exception('Failed to create images directory.');
            }
        }

        $thumbnail_filename = join_paths(DIR_IMAGES, 't' . $file_id . '.' . $ext);
        $filename = join_paths(DIR_IMAGES, $file_id . '.' . $ext);
        switch($type) {
            case IMAGETYPE_GIF:
                imagegif($thumbnail, $thumbnail_filename);
                move_uploaded_file($tmp_filename, $filename);
                break;
            case IMAGETYPE_JPEG:
                imagejpeg($thumbnail, $thumbnail_filename, 100);
                imagejpeg($image, $filename, 100);
                break;
            case IMAGETYPE_PNG:
                imagepng($thumbnail, $thumbnail_filename);
                imagepng($image, $filename);
                break;
        }

        $this->fileId = $file_id;
    }

    public function run() {
        try {
            $pdo = NuPDO::getInstance();
            $pdo->beginTransaction();
            $this->uploadFile();
            $name = array_get_item('name', $_POST);
            $subject = array_get_item('subject', $_POST);
            $message = array_get_item('message', $_POST);
            $password = array_get_item('password', $_POST);
            if (!empty($name))
                $name = substr($name, 0, 50);

            if (!empty($subject))
                $subject = substr($subject, 0, 100);

            if ($this->fileId === null && ($message === null || empty(trim($message))))
                throw new Exception('Message cannot be empty.');
            $message = substr((string)$message, 0, 3000);

            if (!empty($password)) {
                $password_hash = password_hash($password, PASSWORD_DEFAULT);
            }
            else {
                $password = bin2hex(random_bytes(8));
                $password_hash = password_hash($password, PASSWORD_DEFAULT);
            }

            if (!setcookie('slchan_name', $name, time() + COOKIE_EXPIRE_TIME, '/') or
                !setcookie('slchan_pass', $password, time() + COOKIE_EXPIRE_TIME, '/'))
                throw new Exception('Cookie was not set.');
            $_COOKIE['slchan_name'] = $name;
            $_COOKIE['slchan_pass'] = $password;

            $ip_address = $_SERVER['REMOTE_ADDR'];
            $ban_model = new BanModel();
            $ban = $ban_model->getByIpAddress($ip_address);
            if ($ban !== false) {
                // TODO: Dislpay banned posts
                $time_left = 'Never';
                if ($ban['expires'] !== null) {
                    $time_left = ((int)$ban['expires'] - time()) . ' seconds';
                }

                throw new Exception('You are banned: ' . $ban['reason'] . '<br>' . 'Expires: ' . $time_left);
            }

            $post_model = new PostModel();
            $last_post = $post_model->getLastPostByIpAddress($ip_address);
            if ($last_post) {
                $time_left = (POST_COOLDOWN - (time() - (int)$last_post['created']));
                if ($time_left > 0) {
                    throw new Exception('You can not post for another ' . $time_left . ' seconds.');
                }
            }

            $thread_id = array_get_item('thread_id', $_POST);

            $post = $post_model->insert($name, $subject, time(), time(), $message, $this->fileId, $ip_address, $password_hash, $thread_id, false, false);
            $pdo->commit();

            if ($thread_id !== null) {
                $this->redirect("/thread/$thread_id");
            }
        }
        catch (Exception $e) {
            $pdo->rollback();
            $this->error($e->getMessage());
        }

        $this->redirect('/');
    }
}
