<?php
/**
 * Visitor Analytics Tracker
 * Captures basic visitor data that's already being transmitted
 * GDPR compliant - only basic analytics, no personal data
 */

// Only run if not already included and not in admin area
if (!defined('VISITOR_TRACKING_LOADED') && !isset($_SESSION['is_admin'])) {
    define('VISITOR_TRACKING_LOADED', true);

    function detectBot($visitor_data, $conn) {
        $bot_score = 0;
        $indicators = [];
        
        // 1. Check visit frequency - bots often have very high visit rates
        $visits_per_hour = $visitor_data['visit_count'];
        $hours_since_first = max(1, (time() - strtotime($visitor_data['first_visit'])) / 3600);
        $visit_rate = $visits_per_hour / $hours_since_first;
        
        if ($visit_rate > 10) { // More than 10 visits per hour
            $bot_score += 40;
            $indicators[] = 'High visit frequency';
        } elseif ($visit_rate > 5) {
            $bot_score += 20;
            $indicators[] = 'Elevated visit frequency';
        }
        
        // 2. User Agent Analysis
        $user_agent = $visitor_data['user_agent'];
        $bot_signatures = [
            'bot', 'crawler', 'spider', 'scraper', 'curl', 'wget', 'python', 
            'requests', 'scrapy', 'selenium', 'phantomjs', 'headless',
            'googlebot', 'bingbot', 'facebookexternalhit', 'twitterbot'
        ];
        
        foreach ($bot_signatures as $signature) {
            if (stripos($user_agent, $signature) !== false) {
                $bot_score += 50;
                $indicators[] = 'Bot user agent detected';
                break;
            }
        }
        
        // Empty or suspicious user agent
        if (empty($user_agent) || strlen($user_agent) < 10) {
            $bot_score += 30;
            $indicators[] = 'Missing/suspicious user agent';
        }
        
        // 3. Browser/OS combination analysis
        if ($visitor_data['browser_name'] === 'Unknown' && $visitor_data['operating_system'] === 'Unknown') {
            $bot_score += 25;
            $indicators[] = 'Unknown browser/OS';
        }
        
        // 4. Check for page variety - bots often visit same pages repeatedly
        $ip = $visitor_data['ip_address'];
        $page_variety_query = $conn->prepare("
            SELECT COUNT(DISTINCT page_visited) as unique_pages, COUNT(*) as total_visits 
            FROM visitors 
            WHERE ip_address = ?
        ");
        $page_variety_query->bind_param("s", $ip);
        $page_variety_query->execute();
        $page_data = $page_variety_query->get_result()->fetch_assoc();
        $page_variety_query->close();
        
        if ($page_data['total_visits'] > 0) {
            $page_variety_ratio = $page_data['unique_pages'] / $page_data['total_visits'];
            if ($page_variety_ratio < 0.1 && $page_data['total_visits'] > 10) {
                $bot_score += 30;
                $indicators[] = 'Low page variety';
            }
        }
        
        // 5. Referrer analysis
        if (empty($visitor_data['referrer']) && $visitor_data['visit_count'] > 5) {
            $bot_score += 15;
            $indicators[] = 'No referrer pattern';
        }
        
        // 6. Time pattern analysis - bots often have very regular intervals
        $time_pattern_query = $conn->prepare("
            SELECT last_visit, first_visit 
            FROM visitors 
            WHERE ip_address = ? 
            ORDER BY last_visit DESC 
            LIMIT 10
        ");
        $time_pattern_query->bind_param("s", $ip);
        $time_pattern_query->execute();
        $visits = $time_pattern_query->get_result()->fetch_all(MYSQLI_ASSOC);
        $time_pattern_query->close();
        
        // Check for suspiciously regular intervals
        if (count($visits) >= 3) {
            $intervals = [];
            for ($i = 0; $i < count($visits) - 1; $i++) {
                $interval = abs(strtotime($visits[$i]['last_visit']) - strtotime($visits[$i+1]['last_visit']));
                $intervals[] = $interval;
            }
            
            // If intervals are very regular (within 10% variance), likely a bot
            if (count($intervals) >= 2) {
                $avg_interval = array_sum($intervals) / count($intervals);
                $variance = 0;
                foreach ($intervals as $interval) {
                    $variance += pow($interval - $avg_interval, 2);
                }
                $variance = sqrt($variance / count($intervals)) / $avg_interval;
                
                if ($variance < 0.1 && $avg_interval < 3600) { // Very regular and frequent
                    $bot_score += 25;
                    $indicators[] = 'Regular time intervals';
                }
            }
        }
        
        // Determine bot probability
        $bot_probability = min(100, $bot_score);
        $confidence_level = 'low';
        
        if ($bot_probability >= 70) {
            $confidence_level = 'high';
        } elseif ($bot_probability >= 40) {
            $confidence_level = 'medium';
        }
        
        return [
            'is_suspected_bot' => $bot_probability >= 40,
            'bot_probability' => $bot_probability,
            'confidence_level' => $confidence_level,
            'indicators' => $indicators,
            'human_readable' => getBotProbabilityText($bot_probability)
        ];
    }
    
    function getBotProbabilityText($probability) {
        if ($probability >= 80) return 'Very Likely Bot';
        if ($probability >= 60) return 'Likely Bot';
        if ($probability >= 40) return 'Possible Bot';
        if ($probability >= 20) return 'Suspicious Activity';
        return 'Appears Human';
    }
    
    function trackVisitor($conn) {
        try {
            // Get basic info that's already being transmitted
            $ip_address = $_SERVER['REMOTE_ADDR'];
            
            // Check if this IP is blocked
            $blocked_check = $conn->prepare("SELECT id FROM blocked_ips WHERE ip_address = ?");
            $blocked_check->bind_param("s", $ip_address);
            $blocked_check->execute();
            
            if ($blocked_check->get_result()->num_rows > 0) {
                $blocked_check->close();
                return; // Skip tracking for blocked IPs
            }
            $blocked_check->close();
            
            $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
            $referrer = $_SERVER['HTTP_REFERER'] ?? null;
            $page_visited = $_SERVER['REQUEST_URI'] ?? '';
            $language = $_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? null;
            
            // Extract first language if multiple
            if ($language) {
                $language = substr($language, 0, 2);
            }
            
            // Parse user agent for basic info
            $browser_info = parseUserAgent($user_agent);
            
            // Check if this IP has visited before
            $stmt = $conn->prepare("SELECT id, visit_count, first_visit FROM visitors WHERE ip_address = ? ORDER BY last_visit DESC LIMIT 1");
            $stmt->bind_param("s", $ip_address);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows > 0) {
                // Existing visitor - update
                $visitor = $result->fetch_assoc();
                $visit_count = $visitor['visit_count'] + 1;
                
                $update_stmt = $conn->prepare("
                    UPDATE visitors SET 
                    visit_count = ?, 
                    last_visit = NOW(), 
                    page_visited = ?,
                    total_pages_viewed = total_pages_viewed + 1,
                    user_agent = ?,
                    referrer = ?
                    WHERE id = ?
                ");
                $update_stmt->bind_param("isssi", $visit_count, $page_visited, $user_agent, $referrer, $visitor['id']);
                $update_stmt->execute();
                $update_stmt->close();
                
            } else {
                // New visitor - get geo info and insert
                $geo_info = getBasicGeoInfo($ip_address);
                
                $insert_stmt = $conn->prepare("
                    INSERT INTO visitors (
                        ip_address, country_code, country_name, city, 
                        user_agent, browser_name, browser_version, operating_system, device_type,
                        language, referrer, page_visited, visit_count, first_visit, last_visit
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1, NOW(), NOW())
                ");
                
                $insert_stmt->bind_param("ssssssssssss", 
                    $ip_address,
                    $geo_info['country_code'],
                    $geo_info['country_name'], 
                    $geo_info['city'],
                    $user_agent,
                    $browser_info['browser'],
                    $browser_info['version'],
                    $browser_info['os'],
                    $browser_info['device'],
                    $language,
                    $referrer,
                    $page_visited
                );
                
                $insert_stmt->execute();
                $insert_stmt->close();
            }
            
            $stmt->close();
            
        } catch (Exception $e) {
            // Silently fail - don't break the website if tracking fails
            error_log("Visitor tracking error: " . $e->getMessage());
        }
    }

    function parseUserAgent($user_agent) {
        $browser = 'Unknown';
        $version = '';
        $os = 'Unknown';
        $device = 'desktop';
        
        // Browser detection
        if (preg_match('/Chrome\/([0-9\.]+)/', $user_agent, $matches)) {
            $browser = 'Chrome';
            $version = $matches[1];
        } elseif (preg_match('/Firefox\/([0-9\.]+)/', $user_agent, $matches)) {
            $browser = 'Firefox';
            $version = $matches[1];
        } elseif (preg_match('/Safari\/([0-9\.]+)/', $user_agent, $matches)) {
            $browser = 'Safari';
            $version = $matches[1];
        } elseif (preg_match('/Edge\/([0-9\.]+)/', $user_agent, $matches)) {
            $browser = 'Edge';
            $version = $matches[1];
        }
        
        // OS detection
        if (stripos($user_agent, 'windows') !== false) {
            $os = 'Windows';
        } elseif (stripos($user_agent, 'macintosh') !== false || stripos($user_agent, 'mac os') !== false) {
            $os = 'macOS';
        } elseif (stripos($user_agent, 'linux') !== false) {
            $os = 'Linux';
        } elseif (stripos($user_agent, 'android') !== false) {
            $os = 'Android';
            $device = 'mobile';
        } elseif (stripos($user_agent, 'iphone') !== false || stripos($user_agent, 'ipad') !== false) {
            $os = 'iOS';
            $device = stripos($user_agent, 'ipad') !== false ? 'tablet' : 'mobile';
        }
        
        // Mobile detection
        if (preg_match('/Mobile|Android|iPhone|iPad|iPod|BlackBerry|IEMobile|Opera Mini/i', $user_agent)) {
            if (stripos($user_agent, 'tablet') !== false || stripos($user_agent, 'ipad') !== false) {
                $device = 'tablet';
            } else {
                $device = 'mobile';
            }
        }
        
        return [
            'browser' => $browser,
            'version' => $version,
            'os' => $os,
            'device' => $device
        ];
    }

    function getBasicGeoInfo($ip) {
        // Only get basic country info using a free, privacy-friendly service
        // You can replace this with any geolocation service you prefer
        
        $default = ['country_code' => null, 'country_name' => null, 'city' => null];
        
        // Skip local/private IPs
        if ($ip === '127.0.0.1' || strpos($ip, '192.168.') === 0 || strpos($ip, '10.') === 0) {
            return $default;
        }
        
        try {
            // Using ipapi.co - free tier, no registration required
            $context = stream_context_create([
                'http' => [
                    'timeout' => 2, // Quick timeout - don't slow down the site
                    'user_agent' => 'StyleForger Analytics/1.0'
                ]
            ]);
            
            $geo_data = @file_get_contents("http://ipapi.co/{$ip}/json/", false, $context);
            
            if ($geo_data) {
                $data = json_decode($geo_data, true);
                if (isset($data['country_code']) && $data['country_code'] !== null) {
                    return [
                        'country_code' => $data['country_code'] ?? null,
                        'country_name' => $data['country_name'] ?? null,
                        'city' => $data['city'] ?? null
                    ];
                }
            }
        } catch (Exception $e) {
            // Silently fail
        }
        
        return $default;
    }

    // Track the visitor (only run once per page load)
    if (isset($conn) && is_object($conn)) {
        trackVisitor($conn);
    }
}
?>