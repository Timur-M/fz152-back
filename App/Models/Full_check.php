<?php

namespace App\Models;

class Full_check extends Express
{

    /** GET без исключений: [status, headers, body, final_url] или null. */
    public static function fz_try_fetch( $url, $max = 4096, $headers = array() ) {
        try {
            list( $final, $st, $h, , $body ) = self::sg_fetch( $url, false, $max, $headers );
            return array( $st, $h, $body, $final );
        } catch ( \Exception $e ) {
            return null;
        }
    }

    public static function fz_base_url( $host ) {
        $r = self::fz_try_fetch( "https://$host/", 256 );
        return ( $r && $r[0] > 0 ) ? "https://$host" : "http://$host";
    }

    // ---------------------------------------------------------------- безопасность

    public static function fz_sec_check_files( $base ) {
        // путь => [название, признак реального содержимого (regex) или null, критичность]
        $paths = array(
            '/.env'               => array( 'Файл .env с настройками', '/^[A-Z0-9_]+\s*=/m', 'fail' ),
            '/.git/HEAD'          => array( 'Репозиторий .git', '/^ref:\s|^[0-9a-f]{40}/m', 'fail' ),
            '/.git/config'        => array( 'Настройки .git', '/\[core\]/', 'fail' ),
            '/.svn/entries'       => array( 'Репозиторий .svn', '/\A\d+\s*\R\s*\R?dir\b/', 'fail' ),
            '/.DS_Store'          => array( 'Файл .DS_Store', '/Bud1/', 'warn' ),
            '/backup.zip'         => array( 'Архив backup.zip', '/^PK/', 'fail' ),
            '/backup.tar.gz'      => array( 'Архив backup.tar.gz', "/^\x1f\x8b/", 'fail' ),
            '/db.sql'             => array( 'Дамп базы db.sql', '/(CREATE TABLE|INSERT INTO)/i', 'fail' ),
            '/dump.sql'           => array( 'Дамп базы dump.sql', '/(CREATE TABLE|INSERT INTO)/i', 'fail' ),
            '/config.php.bak'     => array( 'Бэкап config.php.bak', '/<\?php|define\(/', 'fail' ),
            '/wp-config.php.bak'  => array( 'Бэкап wp-config.php.bak', '/DB_PASSWORD/', 'fail' ),
            '/wp-config.php~'     => array( 'Копия wp-config.php~', '/DB_PASSWORD/', 'fail' ),
            '/phpinfo.php'        => array( 'Страница phpinfo.php', '/phpinfo\(\)|PHP Version/i', 'fail' ),
            '/info.php'           => array( 'Страница info.php', '/phpinfo\(\)|PHP Version/i', 'fail' ),
            '/server-status'      => array( 'Статус Apache server-status', '/Apache Server Status/i', 'warn' ),
        );
        $items = array();
        $worst = 'ok';
        foreach ( $paths as $path => $p ) {
            $r = self::fz_try_fetch( $base . $path, 4096 );
            // Страницы-заглушки «не найдено» с кодом 200 отсекаем: служебные файлы не начинаются с HTML
            $html_page = preg_match( '/^\s*<(!doctype|html|head|body)/i', $r ? $r[2] : '' );
            $is_page   = in_array( $path, array( '/phpinfo.php', '/info.php', '/server-status' ), true );
            if ( $r && 200 === $r[0] && preg_match( $p[1], $r[2] ) && ( $is_page || ! $html_page ) ) {
                $items[] = self::sg_item( false, $p[0] . ' открыт: ' . $path );
                $worst   = ( 'fail' === $p[2] || 'fail' === $worst ) ? 'fail' : 'warn';
            }
        }
        if ( ! $items ) {
            $items[] = self::sg_item( true, 'Проверено ' . count( $paths ) . ' путей — служебные файлы и бэкапы недоступны' );
        }
        return self::sg_result( 'files', 'Открытые служебные файлы и бэкапы', $worst,
            'ok' === $worst ? 'Служебные файлы закрыты' : 'В открытом доступе лежат файлы, через которые можно получить пароли или данные', $items,
            'Удалите файлы из публичной папки сайта или запретите доступ: location ~ /\. { deny all; } (nginx), RedirectMatch 404 /\..* (Apache).' );
    }

    public static function fz_sec_check_wellknown( $base ) {
        $items = array();
        $r     = self::fz_try_fetch( $base . '/.well-known/security.txt', 4096 );
        $has   = $r && 200 === $r[0] && preg_match( '/^Contact:/mi', $r[2] );
        $items[] = self::sg_item( $has ? true : null, $has ? 'Есть security.txt с контактом для сообщений об уязвимостях' : 'Нет /.well-known/security.txt — исследователям некуда сообщать об уязвимостях' );
        $hidden = array();
        $r      = self::fz_try_fetch( $base . '/robots.txt', 20000 );
        if ( $r && 200 === $r[0] && preg_match_all( '/^Disallow:\s*(\S*(admin|panel|backup|private|secret|manager|cpanel|bitrix\/admin)\S*)/mi', $r[2], $m ) ) {
            $hidden = array_slice( array_unique( $m[1] ), 0, 5 );
        }
        if ( $hidden ) {
            $items[] = self::sg_item( false, 'robots.txt подсказывает служебные разделы: ' . implode( ', ', $hidden ) );
        }
        return self::sg_result( 'wellknown', 'security.txt и robots.txt', $hidden ? 'warn' : 'ok',
            $hidden ? 'robots.txt раскрывает адреса служебных разделов' : 'Публичные служебные файлы в порядке', $items,
            'Добавьте /.well-known/security.txt с Contact: и не перечисляйте адреса админок в robots.txt.' );
    }

    public static function fz_sec_check_admin( $base ) {
        $items = array();
        $worst = 'ok';
        $panels = array(
            '/phpmyadmin/' => array( 'phpMyAdmin', '/phpMyAdmin/i', 'fail' ),
            '/pma/'        => array( 'phpMyAdmin', '/phpMyAdmin/i', 'fail' ),
            '/adminer.php' => array( 'Adminer', '/Adminer/i', 'fail' ),
            '/wp-login.php' => array( 'Вход в WordPress', '/user_login|wp-submit/i', 'warn' ),
            '/administrator/' => array( 'Админка Joomla', '/joomla|mod-login/i', 'warn' ),
            '/bitrix/admin/'  => array( 'Админка 1С-Битрикс', '/bitrix/i', 'warn' ),
        );
        foreach ( $panels as $path => $p ) {
            $r = self::fz_try_fetch( $base . $path, 20000 );
            if ( $r && 200 === $r[0] && preg_match( $p[1], $r[2] ) ) {
                $items[] = self::sg_item( false, $p[0] . ' доступен всем: ' . $path );
                $worst   = ( 'fail' === $p[2] || 'fail' === $worst ) ? 'fail' : 'warn';
            }
        }
        foreach ( array( '/wp-content/uploads/', '/uploads/', '/upload/', '/files/' ) as $dir ) {
            $r = self::fz_try_fetch( $base . $dir, 20000 );
            if ( $r && 200 === $r[0] && preg_match( '/<title>Index of|Directory listing for/i', $r[2] ) ) {
                $items[] = self::sg_item( false, 'Включён листинг каталога: ' . $dir . ' — видны все загруженные файлы' );
                $worst   = 'fail';
            }
        }
        if ( ! $items ) {
            $items[] = self::sg_item( true, 'Админ-панели баз данных закрыты, листинг каталогов выключен' );
        }
        return self::sg_result( 'admin', 'Админки и листинг каталогов', $worst,
            'ok' === $worst ? 'Админки и каталоги закрыты' : 'Найдены открытые админки или списки файлов', $items,
            'Ограничьте доступ к админкам по IP или паролю (allow/deny в nginx, Require ip в Apache), выключите autoindex.' );
    }

    public static function fz_sec_check_cors( $base ) {
        $items = array();
        $worst = 'ok';
        foreach ( array( '/', '/wp-json/', '/api/' ) as $path ) {
            $r = self::fz_try_fetch( $base . $path, 2048, array( 'Origin: https://evil.example' ) );
            if ( ! $r || $r[0] >= 400 ) {
                continue;
            }
            $acao  = self::sg_header( $r[1], 'Access-Control-Allow-Origin' );
            $creds = strtolower( (string) self::sg_header( $r[1], 'Access-Control-Allow-Credentials' ) ) === 'true';
            if ( 'https://evil.example' === $acao ) {
                $items[] = self::sg_item( false, "$path: сервер разрешает запросы с любого сайта (отражает Origin)" . ( $creds ? ' вместе с cookie' : '' ) );
                $worst   = $creds ? 'fail' : 'warn';
            } elseif ( '*' === $acao && $creds ) {
                $items[] = self::sg_item( false, "$path: Access-Control-Allow-Origin: * вместе с Allow-Credentials" );
                $worst   = 'fail';
            }
        }
        if ( ! $items ) {
            $items[] = self::sg_item( true, 'CORS не открывает данные посторонним сайтам' );
        }
        return self::sg_result( 'cors', 'Настройки CORS', $worst, 'ok' === $worst ? 'CORS настроен безопасно' : 'Чужие сайты могут читать ответы вашего сервера', $items,
            'Разрешайте в Access-Control-Allow-Origin только свои домены и не отражайте присланный Origin.' );
    }

    public static function fz_sec_check_page( $base, $html, $headers ) {
        $items = array();
        $worst = 'ok';
        $set   = function ( $level ) use ( &$worst ) {
            if ( 'fail' === $level || ( 'warn' === $level && 'ok' === $worst ) ) {
                $worst = $level;
            }
        };
        $host  = self::wp_parse_url( $base, PHP_URL_HOST );

        // Смешанный контент
        if ( 0 === strpos( $base, 'https://' ) && preg_match_all( '/<(?:script|img|iframe|link|source|video|audio)[^>]+(?:src|href)=["\'](http:\/\/[^"\']+)/i', $html, $m ) ) {
            $items[] = self::sg_item( false, 'Смешанный контент (ресурсы по http://): ' . count( $m[1] ) . ' шт., например ' . self::esc_url_raw( $m[1][0] ) );
            $set( 'warn' );
        }
        // Слабая CSP
        $csp = (string) self::sg_header( $headers, 'Content-Security-Policy' );
        if ( $csp && preg_match( "/'unsafe-inline'|'unsafe-eval'|script-src[^;]*\*/", $csp ) ) {
            $items[] = self::sg_item( false, "CSP ослаблена: unsafe-inline / unsafe-eval / * в script-src" );
            $set( 'warn' );
        }
        // Комментарии с внутренними данными
        if ( preg_match_all( '/<!--(.*?)-->/s', $html, $cm ) ) {
            foreach ( $cm[1] as $c ) {
                if ( preg_match( '/(password|passwd|пароль|api[_-]?key|secret|192\.168\.|10\.\d+\.\d+\.\d+|localhost|TODO|FIXME)/i', $c ) ) {
                    $items[] = self::sg_item( false, 'В HTML-комментариях есть внутренние данные (пароли, адреса, заметки разработчиков)' );
                    $set( 'warn' );
                    break;
                }
            }
        }
        // Внешние скрипты без SRI
        $no_sri = array();
        if ( preg_match_all( '/<script\b[^>]*\bsrc=["\'](https?:)?\/\/([^\/"\']+)[^"\']*["\'][^>]*>/i', $html, $sm, PREG_SET_ORDER ) ) {
            foreach ( $sm as $tag ) {
                if ( strcasecmp( $tag[2], $host ) && ! preg_match( '/\bintegrity=/i', $tag[0] ) && ! preg_match( '/(yandex|mc\.yandex|smartcaptcha|vk\.com|mail\.ru)/i', $tag[2] ) ) {
                    $no_sri[ $tag[2] ] = true;
                }
            }
        }
        if ( $no_sri ) {
            $items[] = self::sg_item( false, 'Скрипты со сторонних CDN без integrity (SRI): ' . implode( ', ', array_slice( array_keys( $no_sri ), 0, 4 ) ) );
            $set( 'warn' );
        }
        // Устаревшие библиотеки
        if ( preg_match( '/jquery[.-]?(\d+\.\d+(?:\.\d+)?)(?:\.min)?\.js|jquery\.min\.js\?ver=(\d+\.\d+(?:\.\d+)?)/i', $html, $jq ) ) {
            $v = $jq[1] ? $jq[1] : $jq[2];
            if ( version_compare( $v, '3.5.0', '<' ) ) {
                $items[] = self::sg_item( false, "jQuery $v — есть известные уязвимости XSS (CVE-2020-11022, CVE-2020-11023), обновите до 3.5+" );
                $set( 'warn' );
            }
        }
        if ( preg_match( '/bootstrap[.-]?(\d+\.\d+\.\d+)(?:\.min)?\.js|bootstrap(?:\.bundle)?\.min\.js\?ver=(\d+\.\d+\.\d+)/i', $html, $bs ) ) {
            $v = $bs[1] ? $bs[1] : $bs[2];
            if ( version_compare( $v, '3.4.1', '<' ) || ( version_compare( $v, '4.0.0', '>=' ) && version_compare( $v, '4.3.1', '<' ) ) ) {
                $items[] = self::sg_item( false, "Bootstrap $v — известная уязвимость XSS (CVE-2019-8331)" );
                $set( 'warn' );
            }
        }
        // Формы: пароль по http и признаки CSRF
        if ( preg_match_all( '/<form\b[^>]*>(.*?)<\/form>/is', $html, $fm, PREG_SET_ORDER ) ) {
            foreach ( $fm as $f ) {
                $is_login = preg_match( '/type=["\']password["\']/i', $f[1] );
                if ( $is_login && ( 0 === strpos( $base, 'http://' ) || preg_match( '/action=["\']http:\/\//i', $f[0] ) ) ) {
                    $items[] = self::sg_item( false, 'Форма с паролем отправляется без шифрования (HTTP)' );
                    $set( 'fail' );
                }
                if ( preg_match( '/method=["\']post["\']/i', $f[0] ) && ! preg_match( '/name=["\'][^"\']*(csrf|token|nonce|_wpnonce|authenticity)[^"\']*["\']/i', $f[1] ) && ! preg_match( '/type=["\']search["\']/i', $f[1] ) ) {
                    $items[] = self::sg_item( null, 'POST-форма без видимого CSRF-токена — проверьте защиту на сервере' );
                    break;
                }
            }
        }
        // Source maps
        if ( preg_match( '/sourceMappingURL=\S+\.map/i', $html ) ) {
            $items[] = self::sg_item( false, 'На странице подключены source maps — исходный код фронтенда доступен' );
            $set( 'warn' );
        }
        if ( ! $items ) {
            $items[] = self::sg_item( true, 'Смешанного контента, слабой CSP, утечек в комментариях и устаревших библиотек не найдено' );
        }
        return self::sg_result( 'page', 'Код страницы, скрипты и формы', $worst,
            'ok' === $worst ? 'Проблем в коде главной страницы не найдено' : 'В коде страницы есть уязвимые места', $items,
            'Переведите все ресурсы на https, обновите jQuery/Bootstrap, добавьте integrity к скриптам с CDN, уберите комментарии и source maps с рабочего сайта.' );
    }

    public static function fz_sec_check_mail( $host ) {
        $domain = preg_replace( '/^www\./', '', $host );
        $items  = array();
        $worst  = 'ok';
        $txt    = function ( $name ) {
            $out = array();
            foreach ( (array) @dns_get_record( $name, DNS_TXT ) as $r ) {
                $out[] = isset( $r['txt'] ) ? $r['txt'] : implode( '', (array) ( $r['entries'] ?? array() ) );
            }
            return $out;
        };
        $spf = array_filter( $txt( $domain ),  function ( $t ) { return 0 === stripos( $t, 'v=spf1' ); } );
        self::log('mail 1','main');
        if ( ! $spf ) {
            $items[] = self::sg_item( false, "SPF не настроен для $domain" );
            $worst   = 'fail';
        } else {
            $spf     = reset( $spf );
            $weak    = preg_match( '/\+all/i', $spf );
            $items[] = self::sg_item( ! $weak, 'SPF: ' . mb_substr( $spf, 0, 120 ) . ( $weak ? ' — «+all» разрешает отправку всем' : '' ) );
            if ( $weak ) {
                $worst = 'fail';
            }
        }
        $dmarc = array_filter( $txt( '_dmarc.' . $domain ), function ( $t ) { return 0 === stripos( $t, 'v=DMARC1' ); } );
        self::log('mail 2','main');
        if ( ! $dmarc ) {
            $items[] = self::sg_item( false, 'DMARC не настроен' );
            $worst   = 'fail';
        } else {
            $d       = reset( $dmarc );
            $none    = preg_match( '/p=none/i', $d );
            $items[] = self::sg_item( ! $none, 'DMARC: ' . mb_substr( $d, 0, 120 ) . ( $none ? ' — политика none только наблюдает, письма-подделки не отклоняются' : '' ) );
            if ( $none && 'ok' === $worst ) {
                $worst = 'warn';
            }
        }
        $dkim = '';
        foreach ( array( 'default', 'mail', 'dkim', 'google', 'selector1', 'selector2', 'k1', 'yandex', 'mx', 'smtp' ) as $sel ) {
            foreach ( $txt( "$sel._domainkey.$domain" ) as $t ) {
                self::log('mail 3 - '.time(),'main');
                if ( false !== stripos( $t, 'p=' ) ) {
                    $dkim = $sel;
                    break 2;
                }
            }
        }
        $items[] = self::sg_item( $dkim ? true : null, $dkim ? "DKIM найден (селектор $dkim)" : 'DKIM не найден по распространённым селекторам — проверьте у почтового провайдера' );
        return self::sg_result( 'mail', 'Почта домена: SPF, DKIM, DMARC', $worst,
            'ok' === $worst ? 'Почтовые записи настроены' : 'От имени вашего домена можно рассылать фишинг', $items,
            'Добавьте TXT-записи: SPF (v=spf1 include:… -all), DMARC (v=DMARC1; p=quarantine; rua=mailto:…) и включите DKIM у почтового провайдера.' );
    }

    /** Полная автоматическая проверка безопасности. */
    public static function fz_run_security_full( $host ) {
        self::log('start','main');
        $report = self::sg_run_audit( $host ); // 5 базовых проверок
        self::log('1','main');
        $base   = self::fz_base_url( $host );
        $home   = self::fz_try_fetch( $base . '/', 1048576 );
        self::log('2','main');
        $html   = $home ? $home[2] : '';
        $hdrs   = $home ? $home[1] : array();
        $report['checks'][] = self::fz_sec_check_files( $base );
        self::log('3','main');
        $report['checks'][] = self::fz_sec_check_admin( $base );
        self::log('4','main');
        $report['checks'][] = self::fz_sec_check_wellknown( $base );
        self::log('5','main');
        $report['checks'][] = self::fz_sec_check_cors( $base );
        self::log('6','main');
        $report['checks'][] = self::fz_sec_check_page( $base, $html, $hdrs );
        self::log('7','main');
        $report['checks'][] = self::fz_sec_check_mail( $host );
        self::log('8','main');
        return self::fz_score_report( $report );
    }

    public static function fz_score_report( $report ) {
        $points = array( 'ok' => 2, 'warn' => 1, 'fail' => 0 );
        $sum    = 0;
        foreach ( $report['checks'] as $c ) {
            $sum += $points[ $c['status'] ];
        }
        $report['score'] = (int) round( 100 * $sum / ( 2 * max( 1, count( $report['checks'] ) ) ) );
        return $report;
    }

    // ---------------------------------------------------------------- 152-ФЗ

    /** Внутренние страницы, где обычно есть формы: контакты, заказ, регистрация. */
    public static function fz_pd_find_pages( \DOMDocument $dom, $base ) {
        $host  = self::wp_parse_url( $base, PHP_URL_HOST );
        $found = array();
        foreach ( $dom->getElementsByTagName( 'a' ) as $a ) {
            $href = self::make_absolute_url( $a->getAttribute( 'href' ), $base . '/' );
            $sig  = mb_strtolower( $a->textContent . ' ' . $href );
            if ( self::wp_parse_url( $href, PHP_URL_HOST ) !== $host || preg_match( '/\.(pdf|jpg|png|zip|docx?)$/i', $href ) ) {
                continue;
            }
            if ( preg_match( '/контакт|contact|kontakt|обратн|callback|feedback|заказ|order|zakaz|корзин|cart|basket|регистрац|register|signup|запис|zapis|заявк|zayavk|оформ/u', $sig ) ) {
                $found[ strtok( $href, '#' ) ] = true;
            }
            if ( count( $found ) >= 5 ) {
                break;
            }
        }
        return array_keys( $found );
    }

    public static function fz_pd_check_policy_content( \DOMDocument $dom, $base, $trackers ) {
        $policy = null;
        foreach ( array( '/политик|конфиденциальн|privacy|policy/u', '/персональн|personal/u' ) as $pattern ) {
            foreach ( $dom->getElementsByTagName( 'a' ) as $a ) {
                $sig = mb_strtolower( $a->textContent . ' ' . $a->getAttribute( 'href' ) );
                if ( preg_match( $pattern, $sig ) && ! preg_match( '/cookie|куки|согласи|consent/u', $sig ) ) {
                    $policy = self::make_absolute_url( $a->getAttribute( 'href' ), $base . '/' );
                    break 2;
                }
            }
        }
        if ( ! $policy ) {
            return self::sg_result( 'policy_content', 'Содержание политики', 'fail', 'Политика не найдена', array( self::sg_item( false, 'Нет ссылки на политику обработки персональных данных' ) ),
                'Опубликуйте политику — готовый текст под ваш сайт есть в разделе «Документы» отчёта.' );
        }
        $r    = self::fz_try_fetch( $policy, 1048576 );
        $text = $r && 200 === $r[0] ? mb_strtolower( self::wp_strip_all_tags( $r[2] ) ) : '';
        if ( '' === $text ) {
            return self::sg_result( 'policy_content', 'Содержание политики', 'fail', 'Страница политики не открывается', array( self::sg_item( false, $policy ) ), 'Проверьте ссылку на политику.' );
        }
        $req   = array(
            'Указан оператор и его ИНН/ОГРН'           => '/инн|огрн/u',
            'Указаны цели обработки'                   => '/цел[ьи]/u',
            'Перечислены обрабатываемые данные'        => '/(e-?mail|почт|телефон|имя)/u',
            'Указаны сроки хранения'                   => '/срок/u',
            'Описаны права субъекта'                   => '/прав[ао]? (субъекта|пользовател)|вправе/u',
            'Описан порядок отзыва согласия'           => '/отзыв|отозвать/u',
            'Упомянуты cookie'                         => '/cookie|куки/u',
            'Указано, передаются ли данные за рубеж'   => '/трансгранич/u',
            'Указано место хранения (локализация в РФ)' => '/территори[ия] российской|на территории рф|в российской федерации/u',
        );
        if ( in_array( 'Яндекс Метрика', $trackers, true ) ) {
            $req['Упомянута Яндекс Метрика'] = '/метрик/u';
        }
        $items   = array();
        $missing = 0;
        foreach ( $req as $label => $re ) {
            $ok      = (bool) preg_match( $re, $text );
            $missing += $ok ? 0 : 1;
            $items[] = self::sg_item( $ok, $label );
        }
        $st = 0 === $missing ? 'ok' : ( $missing <= 2 ? 'warn' : 'fail' );
        return self::sg_result( 'policy_content', 'Содержание политики', $st, 0 === $missing ? 'Политика содержит обязательные разделы' : "Не хватает разделов: $missing", $items,
            'Дополните политику недостающими разделами — актуальный текст под ваш сайт сформирован в разделе «Документы».' );
    }

    public static function fz_pd_check_inner_pages( $pages ) {
        $items = array();
        $worst = 'ok';
        foreach ( $pages as $url ) {
            $r = self::fz_try_fetch( $url, 1048576 );
            if ( ! $r || 200 !== $r[0] ) {
                continue;
            }
            $dom   = self::sg_pd_load_dom( $r[2] );
            $path  = self::wp_parse_url( $url, PHP_URL_PATH ) ?: '/';
            $c     = self::sg_pd_check_consent( $dom );
            $f     = self::sg_pd_check_foreign( $r[2] );
            $h     = self::sg_pd_check_https( $r[3], $dom );
            foreach ( array( $c, $f, $h ) as $chk ) {
                if ( 'ok' !== $chk['status'] ) {
                    $items[] = self::sg_item( false, $path . ': ' . $chk['summary'] );
                    $worst   = ( 'fail' === $chk['status'] || 'fail' === $worst ) ? 'fail' : 'warn';
                }
            }
        }
        if ( ! $pages ) {
            $items[] = self::sg_item( null, 'Ссылок на страницы с формами (контакты, заказ, регистрация) на главной не найдено' );
        } elseif ( ! $items ) {
            $items[] = self::sg_item( true, 'Проверено страниц с формами: ' . count( $pages ) . ' — нарушений нет' );
        }
        return self::sg_result( 'inner', 'Формы на внутренних страницах', $worst,
            'ok' === $worst ? 'Внутренние страницы в порядке' : 'На внутренних страницах есть нарушения', $items,
            'Исправьте согласие, HTTPS и иностранные сервисы на каждой странице с формой.' );
    }

    public static function fz_pd_check_rkn( $inn ) {
        $inn = preg_replace( '/\D/', '', (string) ( $inn ?? '' ) );
        if ( ! $inn ) {
            return self::sg_result( 'rkn', 'Реестр операторов Роскомнадзора', 'warn', 'Укажите ИНН оператора в настройках сайта',
                array( self::sg_item( null, 'Без ИНН сверить запись в реестре нельзя' ) ), 'Заполните реквизиты оператора в настройках сайта в кабинете.' );
        }
        return self::sg_result( 'rkn', 'Реестр операторов Роскомнадзора', 'warn', 'Проверьте запись в реестре по ИНН ' . $inn,
            array( self::sg_item( null, 'Реестр защищён от автоматических запросов: откройте pd.rkn.gov.ru → «Реестр операторов» и найдите ИНН ' . $inn ) ),
            'Если записи нет — подайте уведомление об обработке персональных данных на Госуслугах или pd.rkn.gov.ru.' );
    }

    /** Полная автоматическая проверка 152-ФЗ. Возвращает отчёт и данные для генерации документов. */
    public static function fz_run_pd_full( $host, $inn = '' ) {
        $report = self::sg_run_pd152( $host ); // 5 базовых проверок по главной
        $base   = self::fz_base_url( $host );
        $home   = self::fz_try_fetch( $base . '/', 2097152 );
        $html   = $home ? $home[2] : '';
        $dom    = self::sg_pd_load_dom( $html ? $html : '<html></html>' );
        $trackers = array();
        foreach ( self::sg_pd_trackers() as $needle => $name ) {
            if ( false !== stripos( $html, $needle ) ) {
                $trackers[ $name ] = true;
            }
        }
        $foreign = array();
        foreach ( self::sg_pd_foreign_services() as $needle => $info ) {
            if ( false !== stripos( $html, $needle ) ) {
                $foreign[ $info[0] ] = true;
            }
        }
        $pages = self::fz_pd_find_pages( $dom, $base );
        $fields = self::fz_pd_detect_fields( $html );
        foreach ( $pages as $p ) {
            $r = self::fz_try_fetch( $p, 1048576 );
            if ( $r && 200 === $r[0] ) {
                $fields = array_unique( array_merge( $fields, self::fz_pd_detect_fields( $r[2] ) ) );
            }
        }

        $report['checks'][] = self::fz_pd_check_policy_content( $dom, $base, array_keys( $trackers ) );
        $report['checks'][] = self::fz_pd_check_inner_pages( $pages );
        $report['checks'][] = self::sg_check_cookies( $home ? $home[1] : array(), 0 === strpos( $base, 'https://' ) );
        $report['checks'][] = self::fz_sec_check_files( $base );
        $report['checks'][] = self::fz_sec_check_admin( $base );
        
        if(!empty($inn)){
            $report['checks'][] = self::fz_pd_check_rkn( $inn );
        }
        
        $report = self::fz_score_report( $report );
        $report['detected'] = array(
            'fields'   => $fields,
            'trackers' => array_keys( $trackers ),
            'foreign'  => array_keys( $foreign ),
            'pages'    => $pages,
        );
        return $report;
    }

    /** Какие персональные данные собирают формы на странице. */
    public static function fz_pd_detect_fields( $html ) {
        $map = array(
            'имя'                   => '/name=["\'][^"\']*(name|fio|имя)|autocomplete=["\'](given-|family-)?name/i',
            'номер телефона'        => '/type=["\']tel["\']|name=["\'][^"\']*(phone|tel)/i',
            'адрес электронной почты' => '/type=["\']email["\']|name=["\'][^"\']*mail/i',
            'адрес доставки'        => '/name=["\'][^"\']*(address|adres|city|street)/i',
            'текст сообщения'       => '/<textarea/i',
        );
        $out = array();
        foreach ( $map as $label => $re ) {
            if ( preg_match( $re, $html ) ) {
                $out[] = $label;
            }
        }
        return $out;
    }

}