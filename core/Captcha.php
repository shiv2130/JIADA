<?php
/**
 * JIADA Portal - CAPTCHA System
 * Secure CAPTCHA for all public forms
 */
if (!defined('JIADA_APP')) die('Direct access not permitted');

class Captcha {
    private static $length = 6;
    private static $expire = 300; // 5 minutes
    
    /**
     * Generate CAPTCHA text and store in session
     */
    public static function generate() {
        if (session_status() === PHP_SESSION_NONE) session_start();
        
        $chars = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnpqrstuvwxyz23456789';
        $captcha = '';
        for ($i = 0; $i < self::$length; $i++) {
            $captcha .= $chars[random_int(0, strlen($chars) - 1)];
        }
        
        $_SESSION['captcha_text'] = $captcha;
        $_SESSION['captcha_time'] = time();
        
        return self::generateImage($captcha);
    }
    
    /**
     * Generate CAPTCHA image
     */
    private static function generateImage($text) {
        $width = 180;
        $height = 50;
        $image = imagecreatetruecolor($width, $height);
        
        // Background
        $bg = imagecolorallocate($image, 240, 240, 240);
        imagefill($image, 0, 0, $bg);
        
        // Add noise lines
        for ($i = 0; $i < 8; $i++) {
            $color = imagecolorallocate($image, rand(150, 200), rand(150, 200), rand(150, 200));
            imageline($image, rand(0, $width), rand(0, $height), rand(0, $width), rand(0, $height), $color);
        }
        
        // Add noise dots
        for ($i = 0; $i < 100; $i++) {
            $color = imagecolorallocate($image, rand(150, 200), rand(150, 200), rand(150, 200));
            imagesetpixel($image, rand(0, $width), rand(0, $height), $color);
        }
        
        // Add text
        $textColor = imagecolorallocate($image, 50, 50, 50);
        $font = __DIR__ . '/../assets/fonts/captcha.ttf';
        
        // Use built-in font if TTF not available
        if (file_exists($font)) {
            imagettftext($image, 20, rand(-10, 10), 20, 35, $textColor, $font, $text);
        } else {
            // Use built-in font
            $x = 15;
            for ($i = 0; $i < strlen($text); $i++) {
                $char = $text[$i];
                $y = rand(15, 25);
                imagechar($image, 5, $x, $y, $char, $textColor);
                $x += 22;
            }
        }
        
        // Add border
        $borderColor = imagecolorallocate($image, 180, 180, 180);
        imagerectangle($image, 0, 0, $width - 1, $height - 1, $borderColor);
        
        // Output
        ob_start();
        imagepng($image);
        imagedestroy($image);
        return 'data:image/png;base64,' . base64_encode(ob_get_clean());
    }
    
    /**
     * Verify CAPTCHA
     */
    public static function verify($input) {
        if (session_status() === PHP_SESSION_NONE) session_start();
        
        if (!isset($_SESSION['captcha_text']) || !isset($_SESSION['captcha_time'])) {
            return false;
        }
        
        // Check expiry
        if (time() - $_SESSION['captcha_time'] > self::$expire) {
            unset($_SESSION['captcha_text'], $_SESSION['captcha_time']);
            return false;
        }
        
        $valid = hash_equals(strtolower($_SESSION['captcha_text']), strtolower(trim($input)));
        
        // Clear after verification
        unset($_SESSION['captcha_text'], $_SESSION['captcha_time']);
        
        return $valid;
    }
    
    /**
     * Get CAPTCHA HTML field
     */
    public static function getField() {
        $captchaData = self::generate();
        return '
        <div class="captcha-group" style="margin-bottom: 20px;">
            <label style="display: block; margin-bottom: 8px; font-weight: 500;">Security Verification *</label>
            <div style="display: flex; align-items: center; gap: 15px;">
                <img id="captchaImage" src="' . $captchaData . '" alt="CAPTCHA" style="border: 1px solid #ddd; border-radius: 4px; cursor: pointer;" onclick="refreshCaptcha()" title="Click to refresh">
                <div>
                    <input type="text" name="captcha" id="captchaInput" class="form-control" placeholder="Enter CAPTCHA" required autocomplete="off" style="width: 150px;">
                    <small style="color: #666; cursor: pointer;" onclick="refreshCaptcha()">🔄 Refresh</small>
                </div>
            </div>
            <small style="color: #999;">Case insensitive. Click image to refresh.</small>
        </div>
        <script>
        function refreshCaptcha() {
            fetch("<?php echo APP_URL; ?>/api/captcha/generate")
                .then(r => r.json())
                .then(data => {
                    if(data.success) {
                        document.getElementById("captchaImage").src = data.image;
                        document.getElementById("captchaInput").value = "";
                    }
                });
        }
        </script>';
    }
}