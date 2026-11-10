<?php

namespace App\Models;

define( 'SG_UA', 'Mozilla/5.0 (compatible; 152fz-bot/1.0; +https://xn--152--84d1f.xn--p1acf/)' );
define( 'SG_TIMEOUT', 8 );
define( 'SG_MAX_REDIRECTS', 5 );

class Express extends Base
{
    /** Иностранные сервисы: шаблон в HTML => [название, критичность: fail — передача ПД, warn — риск]. */
    public static function sg_pd_foreign_services() {
        return array(
            'google-analytics.com'          => array( 'Google Analytics', 'fail' ),
            'googletagmanager.com'          => array( 'Google Tag Manager / gtag.js', 'fail' ),
            'connect.facebook.net'          => array( 'Meta Pixel (Facebook)', 'fail' ),
            'analytics.tiktok.com'          => array( 'TikTok Pixel', 'fail' ),
            'static.hotjar.com'             => array( 'Hotjar', 'fail' ),
            'clarity.ms'                    => array( 'Microsoft Clarity', 'fail' ),
            'js.hs-scripts.com'             => array( 'HubSpot', 'fail' ),
            'widget.intercom.io'            => array( 'Intercom', 'fail' ),
            'embed.tawk.to'                 => array( 'Tawk.to', 'fail' ),
            'fonts.googleapis.com'          => array( 'Google Fonts', 'warn' ),
            'fonts.gstatic.com'             => array( 'Google Fonts', 'warn' ),
            'google.com/recaptcha'          => array( 'Google reCAPTCHA', 'warn' ),
            'gstatic.com/recaptcha'         => array( 'Google reCAPTCHA', 'warn' ),
            'recaptcha.net'                 => array( 'Google reCAPTCHA', 'warn' ),
            'youtube.com/embed'             => array( 'Видео YouTube', 'warn' ),
            'youtube-nocookie.com'          => array( 'Видео YouTube', 'warn' ),
            'maps.googleapis.com'           => array( 'Google Maps', 'warn' ),
            'google.com/maps/embed'         => array( 'Google Maps', 'warn' ),
            'ajax.googleapis.com'           => array( 'Библиотеки с серверов Google', 'warn' ),
        );
    }

    /** Счётчики и пиксели, которые должны загружаться только после согласия на cookie. */
    public static function sg_pd_trackers() {
        return array(
            'mc.yandex.ru/metrika'  => 'Яндекс Метрика',
            'mc.yandex.ru/watch'    => 'Яндекс Метрика',
            'top-fwz1.mail.ru'      => 'Top.Mail.Ru',
            'vk.com/js/api/openapi' => 'Пиксель VK',
            'vk.ru/js/api/openapi'  => 'Пиксель VK',
            'googletagmanager.com'  => 'Google Tag Manager / gtag.js',
            'google-analytics.com'  => 'Google Analytics',
            'connect.facebook.net'  => 'Meta Pixel',
            'analytics.tiktok.com'  => 'TikTok Pixel',
            'static.hotjar.com'     => 'Hotjar',
            'clarity.ms'            => 'Microsoft Clarity',
        );
    }

    public static function sg_pd_load_dom( $html ) {
        $dom = new \DOMDocument();
        libxml_use_internal_errors( true );
        $dom->loadHTML( '<?xml encoding="UTF-8">' . $html, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING );
        libxml_clear_errors();
        return $dom;
    }

    /** Скрипты, которые браузер выполнит сразу (а не отложенные блокировщиком cookie через type="text/plain"). */
    public static function sg_pd_executable_scripts( \DOMDocument $dom ) {
        $out = array();
        foreach ( $dom->getElementsByTagName( 'script' ) as $s ) {
            $type = strtolower( trim( $s->getAttribute( 'type' ) ) );
            if ( '' === $type || in_array( $type, array( 'text/javascript', 'application/javascript', 'module' ), true ) ) {
                $out[] = $s->getAttribute( 'src' ) . "\n" . $s->textContent;
            }
        }
        return $out;
    }

    public static function sg_pd_check_policy( \DOMDocument $dom, $base_url, $trackers ) {
        $advice = 'Опубликуйте политику обработки персональных данных и поставьте ссылку на неё в подвал и рядом с каждой формой.';
        $found  = null;
        // Сначала явные «политика / конфиденциальность», затем «обработка персональных данных».
        // Ссылки на cookie-политику и на документ согласия политикой не считаем.
        foreach ( array( '/политик|конфиденциальн|privacy|policy/u', '/персональн|personal/u' ) as $pattern ) {
            foreach ( $dom->getElementsByTagName( 'a' ) as $a ) {
                $text = mb_strtolower( trim( $a->textContent ) );
                $sig  = $text . ' ' . mb_strtolower( $a->getAttribute( 'href' ) );
                if ( preg_match( $pattern, $sig ) && ! preg_match( '/cookie|куки|согласи|consent|soglasi/u', $sig ) ) {
                    $found = array( trim( $a->textContent ), self::make_absolute_url( $a->getAttribute( 'href' ), $base_url ) );
                    break 2;
                }
            }
        }
        if ( ! $found ) {
            return self::sg_result( 'policy', 'Политика обработки персональных данных', 'fail', 'Ссылка на политику не найдена на главной странице',
                array( self::sg_item( false, 'Нет ссылки с текстом «Политика конфиденциальности» или «Обработка персональных данных»' ) ), $advice );
        }
        $items = array( self::sg_item( true, 'Ссылка найдена: «' . mb_substr( $found[0], 0, 80 ) . '»' ) );

        // Открываем саму политику, если она на том же домене
        if ( self::wp_parse_url( $found[1], PHP_URL_HOST ) !== self::wp_parse_url( $base_url, PHP_URL_HOST ) ) {
            $items[] = self::sg_item( null, 'Политика размещена на другом домене — проверьте её вручную' );
            return self::sg_result( 'policy', 'Политика обработки персональных данных', 'warn', 'Политика опубликована на внешнем ресурсе', $items, $advice );
        }
        try {
            list( , $status, , , $body ) = self::sg_fetch( $found[1], false, 1048576 );
        } catch ( \Exception $e ) {
            $status = 0;
            $body   = '';
        }
        if ( $status >= 400 || 0 === $status ) {
            $items[] = self::sg_item( false, 'Страница политики не открывается' . ( $status ? " (код $status)" : '' ) );
            return self::sg_result( 'policy', 'Политика обработки персональных данных', 'fail', 'Ссылка на политику ведёт на несуществующую страницу', $items, $advice );
        }
        $text  = mb_strtolower( self::wp_strip_all_tags( $body ) );
        $issue = false;
        if ( ! preg_match( '/cookie|куки/u', $text ) ) {
            $items[] = self::sg_item( false, 'Политика не упоминает cookie' );
            $issue   = true;
        }
        if ( ! preg_match( '/срок/u', $text ) ) {
            $items[] = self::sg_item( false, 'В политике не указаны сроки хранения данных' );
            $issue   = true;
        }
        if ( in_array( 'Яндекс Метрика', $trackers, true ) && ! preg_match( '/метрик/u', $text ) ) {
            $items[] = self::sg_item( false, 'На сайте стоит Яндекс Метрика, но политика её не упоминает' );
            $issue   = true;
        }
        if ( ! $issue ) {
            $items[] = self::sg_item( true, 'Политика открывается и упоминает cookie и сроки хранения' );
        }
        return self::sg_result( 'policy', 'Политика обработки персональных данных', $issue ? 'warn' : 'ok',
            $issue ? 'Политика опубликована, но не соответствует сайту полностью' : 'Политика опубликована и доступна', $items,
            'Опишите в политике все поля форм, cookie, счётчики и сроки хранения данных.' );
    }

    public static function sg_pd_check_consent( \DOMDocument $dom ) {
        $advice = 'Добавьте в каждую форму чекбокс согласия: не отмеченный по умолчанию и со ссылкой на отдельный документ «Согласие на обработку персональных данных».';
        $xp     = new \DOMXPath( $dom );
        $forms  = array();
        foreach ( $dom->getElementsByTagName( 'form' ) as $f ) {
            $has_pd = false;
            /** @var DOMElement $in */
            foreach ( $xp->query( './/input|.//textarea', $f ) as $in ) {
                $sig = strtolower( $in->getAttribute( 'type' ) . ' ' . $in->getAttribute( 'name' ) . ' ' . $in->getAttribute( 'autocomplete' ) . ' ' . $in->getAttribute( 'placeholder' ) );
                if ( preg_match( '/email|tel|phone|name|fio|имя|телефон|почт/u', $sig ) && ! preg_match( '/search|^hidden|captcha/', $sig ) ) {
                    $has_pd = true;
                    break;
                }
            }
            if ( $has_pd ) {
                $forms[] = $f;
            }
        }
        if ( ! $forms ) {
            return self::sg_result( 'consent', 'Согласие в формах', 'ok', 'На главной странице нет форм, собирающих персональные данные',
                array( self::sg_item( null, 'Формы на других страницах (контакты, корзина) проверяются в полном аудите' ) ),
                'Проверьте формы на остальных страницах сайта.' );
        }

        $items = array();
        $worst = 'ok';
        foreach ( array_slice( $forms, 0, 5 ) as $n => $f ) {
            $label    = 'Форма ' . ( $n + 1 );
            $boxes    = $xp->query( './/input[@type="checkbox"]', $f );
            $links    = array();
            /** @var DOMElement $a */
            foreach ( $xp->query( './/a', $f ) as $a ) {
                $links[] = mb_strtolower( $a->textContent . ' ' . $a->getAttribute( 'href' ) );
            }
            $links    = implode( ' ', $links );
            if ( 0 === $boxes->length ) {
                $items[] = self::sg_item( false, "$label: нет чекбокса согласия на обработку персональных данных" );
                $worst   = 'fail';
                continue;
            }
            $prechecked = false;
            /** @var DOMElement $b */
            foreach ( $boxes as $b ) {
                if ( $b->hasAttribute( 'checked' ) ) {
                    $prechecked = true;
                }
            }
            if ( $prechecked ) {
                $items[] = self::sg_item( false, "$label: чекбокс согласия отмечен заранее — такое согласие недействительно" );
                $worst   = 'fail';
            } elseif ( ! preg_match( '/согласи|consent|soglasi/u', $links ) ) {
                $items[] = self::sg_item( false, "$label: согласие не оформлено отдельным документом — ссылка ведёт на политику или её нет" );
                $worst   = 'fail' === $worst ? 'fail' : 'warn';
            } else {
                $items[] = self::sg_item( true, "$label: чекбокс не отмечен заранее и ведёт на отдельный документ согласия" );
            }
        }
        $summary = array(
            'ok'   => 'Согласие оформлено правильно',
            'warn' => 'Согласие не оформлено отдельным документом',
            'fail' => 'Формы собирают данные без действительного согласия',
        );
        return self::sg_result( 'consent', 'Согласие в формах', $worst, $summary[ $worst ], $items, $advice );
    }

    public static function sg_pd_check_cookies( $html, \DOMDocument $dom ) {
        $advice     = 'Подключите cookie-баннер и загружайте счётчики только после согласия посетителя.';
        $executable = implode( "\n", self::sg_pd_executable_scripts( $dom ) );
        $all        = $html;
        $immediate  = array();
        $present    = array();
        foreach ( self::sg_pd_trackers() as $needle => $name ) {
            if ( false !== stripos( $all, $needle ) ) {
                $present[ $name ] = true;
                if ( false !== stripos( $executable, $needle ) ) {
                    $immediate[ $name ] = true;
                }
            }
        }
        $banner = (bool) preg_match( '/cookie-?consent|cookieconsent|cookie-?notice|cookie-?banner|cookie-law|complianz|cookiebot|cookieyes|cookie-?bar|gdpr-cookie|используем (файлы )?cookie|используем куки|файлы cookie/iu', $html );

        $items = array();
        foreach ( array_keys( $present ) as $name ) {
            $items[] = self::sg_item( ! isset( $immediate[ $name ] ), $name . ( isset( $immediate[ $name ] ) ? ': загружается сразу при открытии страницы' : ': загружается после согласия' ) );
        }
        $items[] = self::sg_item( $banner ? true : ( $present ? false : null ), $banner ? 'Cookie-баннер найден' : 'Cookie-баннер не найден' );

        if ( ! $present ) {
            return self::sg_result( 'cookies', 'Cookie-баннер и счётчики', 'ok', 'Счётчиков в исходном коде не найдено', $items, $advice );
        }
        if ( ! $banner ) {
            return self::sg_result( 'cookies', 'Cookie-баннер и счётчики', 'fail', 'Счётчики работают, а cookie-баннера нет', $items, $advice );
        }
        if ( $immediate ) {
            return self::sg_result( 'cookies', 'Cookie-баннер и счётчики', 'fail', 'Счётчики загружаются до согласия посетителя', $items, $advice );
        }
        return self::sg_result( 'cookies', 'Cookie-баннер и счётчики', 'ok', 'Счётчики ждут согласия посетителя', $items, $advice );
    }

    public static function sg_pd_check_foreign( $html ) {
        $found = array();
        foreach ( self::sg_pd_foreign_services() as $needle => $info ) {
            if ( false !== stripos( $html, $needle ) ) {
                $found[ $info[0] ] = $info[1];
            }
        }
        if ( ! $found ) {
            return self::sg_result( 'foreign', 'Иностранные сервисы', 'ok', 'Иностранных сервисов в исходном коде не найдено',
                array( self::sg_item( true, 'Google Analytics, Google Fonts, reCAPTCHA, YouTube и пиксели не обнаружены' ) ),
                'Следите, чтобы новые виджеты не передавали данные за рубеж.' );
        }
        $items = array();
        $worst = 'warn';
        foreach ( $found as $name => $level ) {
            $items[] = self::sg_item( false, $name . ( 'fail' === $level ? ' — передаёт персональные данные посетителей за рубеж' : ' — передаёт IP-адреса посетителей за рубеж' ) );
            if ( 'fail' === $level ) {
                $worst = 'fail';
            }
        }
        return self::sg_result( 'foreign', 'Иностранные сервисы', $worst,
            'Найдено иностранных сервисов: ' . count( $found ) . ' — это трансграничная передача данных', $items,
            'Замените сервисы российскими аналогами (Яндекс Метрика, Яндекс SmartCaptcha, локальные шрифты) или подайте уведомление о трансграничной передаче в Роскомнадзор.' );
    }

    public static function sg_pd_check_https( $final_url, \DOMDocument $dom ) {
        $items  = array();
        $ok     = 0 === strpos( $final_url, 'https://' );
        $items[] = self::sg_item( $ok, $ok ? 'Сайт открывается по HTTPS' : 'Сайт открывается без шифрования (HTTP)' );
        $insecure = 0;
        foreach ( $dom->getElementsByTagName( 'form' ) as $f ) {
            if ( 0 === stripos( trim( $f->getAttribute( 'action' ) ), 'http://' ) ) {
                $insecure++;
            }
        }
        if ( $insecure ) {
            $items[] = self::sg_item( false, "Форм, отправляющих данные по HTTP: $insecure" );
        } elseif ( $dom->getElementsByTagName( 'form' )->length ) {
            $items[] = self::sg_item( true, 'Формы отправляют данные по защищённому соединению' );
        }
        $status = ( $ok && ! $insecure ) ? 'ok' : 'fail';
        return self::sg_result( 'https', 'Защита передачи данных', $status,
            'ok' === $status ? 'Данные из форм передаются по HTTPS' : 'Персональные данные передаются без шифрования', $items,
            'Включите HTTPS на всём сайте и укажите в формах адреса отправки с https://.' );
    }

    public static function sg_run_pd152( $raw_url ) {
        $host    = self::sg_normalize_target( $raw_url );
        $started = microtime( true );

        $final = null;
        $body  = '';
        foreach ( array( 'https', 'http' ) as $scheme ) {
            try {
                list( $final, $status, , , $body ) = self::sg_fetch( "$scheme://$host/", false, 2097152 );
                break;
            
            } catch ( \Exception $e ) {
                continue;
            }
        }
        if ( null === $final ) {
            //throw new SG_Audit_Exception( 'Сайт не отвечает' );
        }
        if ( '' === trim( $body ) ) {
            //throw new SG_Audit_Exception( 'Сайт вернул пустую страницу' );
        }

        $dom      = self::sg_pd_load_dom( $body );
        $trackers = array();
        foreach ( self::sg_pd_trackers() as $needle => $name ) {
            if ( false !== stripos( $body, $needle ) ) {
                $trackers[] = $name;
            }
        }
        $checks = array(
            self::sg_pd_check_policy( $dom, $final, $trackers ),
            self::sg_pd_check_consent( $dom ),
            self::sg_pd_check_cookies( $body, $dom ),
            self::sg_pd_check_foreign( $body ),
            self::sg_pd_check_https( $final, $dom ),
        );
        $points = array( 'ok' => 20, 'warn' => 10, 'fail' => 0 );
        $score  = 0;
        foreach ( $checks as $c ) {
            $score += $points[ $c['status'] ];
        }
        return array(
            'host'       => $host,
            'finalUrl'   => $final,
            'score'      => $score,
            'checkedAt'  => date( 'd.m.Y H:i' ),
            'durationMs' => (int) ( ( microtime( true ) - $started ) * 1000 ),
            'checks'     => $checks,
        );
    }

    public static function make_absolute_url(string $maybe_relative_path, string $url): string 
    {
        if (empty($url)) {
            return $maybe_relative_path;
        }

        $url_parts = parse_url($url);
        if (!$url_parts) {
            return $maybe_relative_path;
        }

        $relative_url_parts = parse_url($maybe_relative_path);
        if (!$relative_url_parts) {
            return $maybe_relative_path;
        }

        // Если у "относительного" пути уже есть своя схема/протокол (http, https и т.д.)
        if (!empty($relative_url_parts['scheme'])) {
            return $maybe_relative_path;
        }

        // Строим начало адреса на основе схемы базового URL
        $absolute_path = $url_parts['scheme'] . '://';

        // Обработка безпротокольных ссылок (начинаются с "//", например: //://example.com)
        if (isset($relative_url_parts['host'])) {
            $absolute_path .= $relative_url_parts['host'];
            if (isset($relative_url_parts['port'])) {
                $absolute_path .= ':' . $relative_url_parts['port'];
            }
        } else {
            // Если хоста в путях нет, берем хост и порт из базового URL
            $absolute_path .= $url_parts['host'];
            if (isset($url_parts['port'])) {
                $absolute_path .= ':' . $url_parts['port'];
            }
        }

        // Обработка самого пути (path)
        if (isset($relative_url_parts['path'])) {
            if ('/' === $relative_url_parts['path'][0]) {
                // Если путь начинается с корня сайта: /images/pic.png
                $absolute_path .= $relative_url_parts['path'];
            } else {
                // Если путь относительный: images/pic.png (высчитываем относительно папки базового URL)
                $path = $url_parts['path'] ?? '/';
                $path = dirname($path);
                
                if ('/' !== $path && '\\' !== $path) {
                    $absolute_path .= $path . '/' . $relative_url_parts['path'];
                } else {
                    $absolute_path .= '/' . $relative_url_parts['path'];
                }
            }
        } elseif (isset($url_parts['path'])) {
            // Если в относительном URL вообще нет пути (только query или фрагмент), берем путь из базового
            $absolute_path .= $url_parts['path'];
        }

        // Добавляем GET-параметры (?query=value)
        if (isset($relative_url_parts['query'])) {
            $absolute_path .= '?' . $relative_url_parts['query'];
        }

        // Добавляем якорь/хеш (#hash)
        if (isset($relative_url_parts['fragment'])) {
            $absolute_path .= '#' . $relative_url_parts['fragment'];
        }

        return $absolute_path;
    }

    public static function sg_result( $id, $title, $status, $summary, $items, $advice ) {
        return compact( 'id', 'title', 'status', 'summary', 'items', 'advice' );
    }

    public static function sg_item( $ok, $text ) {
        return array( 'ok' => $ok, 'text' => $text );
    }
   
   /**
    * Полный аналог функции wp_parse_url() из WordPress для чистого PHP.
    * Разбирает переданный URL на компоненты, корректно обрабатывая edge-case ссылки.
    *
    * @param string $url       URL-адрес для разбора.
    * @param int    $component Конкретный компонент для возврата (например, PHP_URL_HOST). По умолчанию -1 (вернуть всё).
    * @return mixed Массив компонентов, строка/инт (если указан $component), null (если компонент не найден) или false при критической ошибке.
    */
    public static function wp_parse_url(string $url, int $component = -1) 
    {
        $to_unset = [];

        // Обработка ссылок без протокола (например: //://example.com)
        if (str_starts_with($url, '//')) {
            $to_unset[] = 'scheme';
            $url        = 'placeholder:' . $url;
        } 
        // Обработка относительных путей (например: /path/to/page?param=1)
        elseif (str_starts_with($url, '/')) {
            $to_unset[] = 'scheme';
            $to_unset[] = 'host';
            $url        = 'placeholder://placeholder' . $url;
        }

        // Вызываем стандартный парсер PHP, подавляя возможные Warning'и
        $parts = @parse_url($url);

        if (false === $parts) {
            return false;
        }

        // Удаляем временные плейсхолдеры, которые мы подставили ранее
        foreach ($to_unset as $key) {
            unset($parts[$key]);
        }

        // Если запрошен массив целиком (по умолчанию)
        if (-1 === $component) {
            return $parts;
        }

        // Если запрошен конкретный компонент, эмулируем поведение внутренней функции WP
        return self::_get_component_from_parsed_url_array($parts, $component);
    }

    /**
    * Вспомогательная функция для получения конкретного компонента из массива (аналог из WP Core).
    */
    public static function _get_component_from_parsed_url_array(array $url_parts, int $component) 
    {
        $key_map = [
            PHP_URL_SCHEME   => 'scheme',
            PHP_URL_HOST     => 'host',
            PHP_URL_PORT     => 'port',
            PHP_URL_USER     => 'user',
            PHP_URL_PASS     => 'pass',
            PHP_URL_PATH     => 'path',
            PHP_URL_QUERY    => 'query',
            PHP_URL_FRAGMENT => 'fragment',
        ];

        if (isset($key_map[$component])) {
            $key = $key_map[$component];
            return $url_parts[$key] ?? null;
        }

        return null;
    }

    /**
    * GET с ручным следованием редиректам. Соединение идёт на заранее проверенный IP
    * (CURLOPT_RESOLVE), чтобы исключить подмену DNS между проверкой и запросом.
    *
    * @return array [final_url, status, headers (lowercase name => [values]), chain, body]
    */
    public static function sg_fetch( $url, $verify = true, $max_body = 65536, $extra_headers = array() ) {
        $chain = array();
        for ( $i = 0; $i <= SG_MAX_REDIRECTS; $i++ ) {
            $p      = self::wp_parse_url( $url );
            $scheme = isset( $p['scheme'] ) ? strtolower( $p['scheme'] ) : 'http';
            $host   = $p['host'];
            $port   = isset( $p['port'] ) ? (int) $p['port'] : ( 'https' === $scheme ? 443 : 80 );
            $ip     = self::sg_resolve_public_ip( $host );
            $ip_str = false !== strpos( $ip, ':' ) ? "[$ip]" : $ip;

            $headers = array();
            $size    = 0;
            $body    = '';
            $ch      = curl_init( $url );
            $opts    = array(
                CURLOPT_RESOLVE        => array( "$host:$port:$ip_str" ),
                CURLOPT_FOLLOWLOCATION => false,
                CURLOPT_TIMEOUT        => SG_TIMEOUT,
                CURLOPT_CONNECTTIMEOUT => 6,
                CURLOPT_USERAGENT      => SG_UA,
                CURLOPT_HTTPHEADER     => array_merge( array( 'Accept: text/html,*/*' ), $extra_headers ),
                CURLOPT_ENCODING       => '', // gzip/br — curl распакует сам
                CURLOPT_SSL_VERIFYPEER => $verify,
                CURLOPT_SSL_VERIFYHOST => $verify ? 2 : 0,
                CURLOPT_PROTOCOLS      => CURLPROTO_HTTP | CURLPROTO_HTTPS,
                CURLOPT_HEADERFUNCTION => function ( $ch, $line ) use ( &$headers ) {
                    $t = trim( $line );
                    if ( 0 === strpos( $t, 'HTTP/' ) ) {
                        $headers = array(); // новый ответ (например, после 100 Continue)
                    } elseif ( '' !== $t && false !== strpos( $t, ':' ) ) {
                        list( $k, $v ) = explode( ':', $t, 2 );
                        $headers[ strtolower( trim( $k ) ) ][] = trim( $v );
                    }
                    return strlen( $line );
                },
                CURLOPT_WRITEFUNCTION  => function ( $ch, $data ) use ( &$size, &$body, $max_body ) {
                    $size += strlen( $data );
                    if ( $size > $max_body ) {
                        return 0; // тело дальше лимита не читаем
                    }
                    $body .= $data;
                    return strlen( $data );
                },
            );
            // if ( sg_ca_bundle() ) {
            //     $opts[ CURLOPT_CAINFO ] = sg_ca_bundle();
            // }
            curl_setopt_array( $ch, $opts );
            curl_exec( $ch );
            $errno  = curl_errno( $ch );
            $error  = curl_error( $ch );
            $status = (int) curl_getinfo( $ch, CURLINFO_RESPONSE_CODE );
            curl_close( $ch );

            if ( $errno && 23 !== $errno ) { // 23 — мы сами прервали чтение тела
                //throw new RuntimeException( $error );
            }

            $chain[] = array( 'url' => $url, 'status' => $status );
            if ( in_array( $status, array( 301, 302, 303, 307, 308 ), true ) && ! empty( $headers['location'][0] ) ) {
                $url = self::make_absolute_url( $headers['location'][0], $url );
                if ( ! preg_match( '~^https?://~i', $url ) ) {
                    break;
                }
                continue;
            }
            return array( $url, $status, $headers, $chain, $body );
        }
        //throw new SG_Audit_Exception( 'Слишком много перенаправлений' );
    }

    /**
    * Полный аналог функции wp_strip_all_tags() из WordPress.
    * Удаляет все HTML-теги, включая содержимое тегов <script> и <style>.
    *
    * @param string $string       Исходная строка для очистки.
    * @param bool   $remove_breaks Если true, удаляет символы перевода строки (\r, \n, \t). Default: false.
    * @return string Очищенная строка.
    */
    public static function wp_strip_all_tags(string $string, bool $remove_breaks = false): string 
    {
        // 1. Полностью удаляем теги <script> и <style> вместе со всем их содержимым
        $string = preg_replace('@<(script|style)[^>]*?>.*?</\\1>@si', '', $string);

        // Если регулярное выражение вернуло ошибку (например, из-за лимитов backtrack), возвращаем пустую строку, как в WP
        if (null === $string) {
            return '';
        }

        // 2. Вырезаем все остальные HTML-теги
        $string = strip_tags($string);

        // 3. Если передан флаг удаления переносов, заменяем их пробелами
        if ($remove_breaks) {
            $string = preg_replace('/[\r\n\t ]+/', ' ', $string);
        }

        // 4. Очищаем пробельные символы и нулевые байты по краям
        return trim($string);
    }

    public static function sg_normalize_target( $raw ) {
        $raw = trim( (string) $raw );
        if ( '' === $raw ) {
            //throw new SG_Audit_Exception( 'Укажите адрес сайта' );
        }
        if ( ! preg_match( '~^https?://~i', $raw ) ) {
            $raw = 'http://' . $raw;
        }
        $host = self::wp_parse_url( $raw, PHP_URL_HOST );
        if ( ! $host ) {
            //throw new SG_Audit_Exception( 'Некорректный адрес сайта' );
        }
        $host = function_exists( 'mb_strtolower' ) ? mb_strtolower( $host, 'UTF-8' ) : strtolower( $host );
        $host = rtrim( $host, '.' );

        // Кириллические домены -> punycode
        if ( preg_match( '/[^\x20-\x7e]/', $host ) ) {
            if ( ! function_exists( 'idn_to_ascii' ) ) {
                //throw new SG_Audit_Exception( 'Укажите домен латиницей (punycode)' );
            }
            $ascii = idn_to_ascii( $host, IDNA_DEFAULT, INTL_IDNA_VARIANT_UTS46 );
            if ( ! $ascii ) {
                //throw new SG_Audit_Exception( 'Некорректный адрес сайта' );
            }
            $host = $ascii;
        }

        if ( strlen( $host ) > 253 || false === strpos( $host, '.' ) || ! preg_match( '/^[a-z0-9.-]+$/', $host ) ) {
            //throw new SG_Audit_Exception( 'Некорректный адрес сайта' );
        }
        return $host;
    }

    /** Резолвит хост и запрещает внутренние адреса (защита от SSRF). */
    public static function sg_resolve_public_ip( $host ) {
        $ips = array();
        if ( filter_var( $host, FILTER_VALIDATE_IP ) ) {
            $ips[] = $host;
        } else {
            foreach ( (array) @dns_get_record( $host, DNS_A ) as $r ) {
                if ( ! empty( $r['ip'] ) ) {
                    $ips[] = $r['ip'];
                }
            }
            foreach ( (array) @dns_get_record( $host, DNS_AAAA ) as $r ) {
                if ( ! empty( $r['ipv6'] ) ) {
                    $ips[] = $r['ipv6'];
                }
            }
            if ( ! $ips ) {
                $ips = (array) @gethostbynamel( $host );
            }
        }
        $ips = array_filter( $ips );
        if ( ! $ips ) {
            //throw new SG_Audit_Exception( 'Домен не найден в DNS' );
        }
        foreach ( $ips as $ip ) {
            if ( ! self::sg_is_public_ip( $ip ) ) {
                //throw new SG_Audit_Exception( 'Проверка внутренних адресов запрещена' );
            }
        }
        return reset( $ips );
    }

    public static function sg_is_public_ip( $ip ) {
        if ( ! filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE ) ) {
            return false;
        }
        // 100.64.0.0/10 (CGNAT) и 0.0.0.0/8 PHP не считает зарезервированными
        if ( filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 ) ) {
            $long = ip2long( $ip );
            if ( ( $long & 0xFFC00000 ) === ( ip2long( '100.64.0.0' ) & 0xFFC00000 ) || ( $long >> 24 ) === 0 ) {
                return false;
            }
        }
        return true;
    }
}