<?php
class FileHandler {
    private $maxFileSize = 5242880; // 5MB
    private $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    private $allowedExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
    private $uploadPath = 'uploads/';
    
    public function __construct($maxSize = null, $uploadPath = null) {
        if ($maxSize) $this->maxFileSize = $maxSize;
        if ($uploadPath) $this->uploadPath = $uploadPath;
        
        // Ensure upload directory exists
        if (!file_exists($this->uploadPath)) {
            if (!mkdir($this->uploadPath, 0777, true)) {
                throw new Exception("Cannot create upload directory: " . $this->uploadPath);
            }
        }
        
        // Make sure directory is writable
        if (!is_writable($this->uploadPath)) {
            chmod($this->uploadPath, 0777);
        }
        
        // Create .htaccess for security
        $this->createHtaccess();
    }
    
    public function uploadImage($file, $prefix = '') {
        try {
            // Debug logging
            error_log("FileHandler: Starting upload process");
            error_log("FileHandler: File info - " . print_r($file, true));
            
            // Validate file
            $validation = $this->validateFile($file);
            if ($validation !== true) {
                error_log("FileHandler: Validation failed - " . $validation);
                throw new Exception($validation);
            }
            
            // Generate secure filename
            $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            $filename = $prefix . time() . '_' . bin2hex(random_bytes(4)) . '.' . $extension;
            $filepath = $this->uploadPath . $filename;
            
            error_log("FileHandler: Target filepath - " . $filepath);
            
            // Try simple move_uploaded_file first
            if (move_uploaded_file($file['tmp_name'], $filepath)) {
                error_log("FileHandler: File uploaded successfully - " . $filename);
                return $filename;
            } else {
                error_log("FileHandler: move_uploaded_file failed");
                throw new Exception("Failed to move uploaded file");
            }
            
        } catch (Exception $e) {
            error_log("FileHandler: Upload error - " . $e->getMessage());
            throw $e;
        }
    }
    
    private function validateFile($file) {
        // Check for upload errors
        if (!isset($file['error'])) {
            return "No file uploaded";
        }
        
        if ($file['error'] !== UPLOAD_ERR_OK) {
            return $this->getUploadErrorMessage($file['error']);
        }
        
        // Check if file exists
        if (!isset($file['tmp_name']) || !file_exists($file['tmp_name'])) {
            return "Uploaded file not found";
        }
        
        // Check file size
        if ($file['size'] > $this->maxFileSize) {
            return "File too large. Maximum size: " . $this->formatBytes($this->maxFileSize);
        }
        
        // Check file extension
        $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($extension, $this->allowedExtensions)) {
            return "Invalid file extension. Allowed: " . implode(', ', $this->allowedExtensions);
        }
        
        // Check MIME type if finfo is available
        if (function_exists('finfo_open')) {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mimeType = finfo_file($finfo, $file['tmp_name']);
            finfo_close($finfo);
            
            if (!in_array($mimeType, $this->allowedTypes)) {
                return "Invalid file type. Detected: " . $mimeType;
            }
        }
        
        // Check if it's actually an image (if GD is available)
        if (function_exists('getimagesize')) {
            $imageInfo = getimagesize($file['tmp_name']);
            if ($imageInfo === false) {
                return "File is not a valid image";
            }
        }
        
        return true;
    }
    
    private function createHtaccess() {
        $htaccessPath = $this->uploadPath . '.htaccess';
        if (!file_exists($htaccessPath)) {
            $content = "# Prevent direct access to uploaded files\n";
            $content .= "Options -Indexes\n";
            $content .= "# Only allow image files\n";
            $content .= "<FilesMatch \"\\.(jpg|jpeg|png|gif|webp)$\">\n";
            $content .= "    Order Allow,Deny\n";
            $content .= "    Allow from all\n";
            $content .= "</FilesMatch>\n";
            $content .= "# Deny everything else\n";
            $content .= "<FilesMatch \"^(?!.*\\.(jpg|jpeg|png|gif|webp)$).*$\">\n";
            $content .= "    Order Deny,Allow\n";
            $content .= "    Deny from all\n";
            $content .= "</FilesMatch>\n";
            
            @file_put_contents($htaccessPath, $content);
        }
    }
    
    public function deleteFile($filename) {
        if ($filename && file_exists($this->uploadPath . $filename)) {
            return @unlink($this->uploadPath . $filename);
        }
        return false;
    }
    
    private function getUploadErrorMessage($error) {
        switch ($error) {
            case UPLOAD_ERR_INI_SIZE:
                return "File exceeds upload_max_filesize directive in php.ini";
            case UPLOAD_ERR_FORM_SIZE:
                return "File exceeds MAX_FILE_SIZE directive in HTML form";
            case UPLOAD_ERR_PARTIAL:
                return "File was only partially uploaded";
            case UPLOAD_ERR_NO_FILE:
                return "No file was uploaded";
            case UPLOAD_ERR_NO_TMP_DIR:
                return "Missing temporary folder";
            case UPLOAD_ERR_CANT_WRITE:
                return "Failed to write file to disk";
            case UPLOAD_ERR_EXTENSION:
                return "File upload stopped by extension";
            default:
                return "Unknown upload error (code: $error)";
        }
    }
    
    private function formatBytes($size, $precision = 2) {
        $units = ['B', 'KB', 'MB', 'GB'];
        for ($i = 0; $size > 1024 && $i < count($units) - 1; $i++) {
            $size /= 1024;
        }
        return round($size, $precision) . ' ' . $units[$i];
    }
}
?>