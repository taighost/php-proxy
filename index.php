<?php
try {
    $ch = curl_init();

    // проверка на ошибку при иннициализации
    if ($ch === false) {
        throw new Exception('failed to initialize');
    }

    $url = 'https://news.ycombinator.com/'.$_SERVER["REQUEST_URI"];

    // Better to explicitly set URL
    curl_setopt($ch, CURLOPT_URL,  $url);
    // опция для получения контента страницы, иначе вернёт просто true, если всё ок
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    // отключаем проверку ssl-сертификата
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, FALSE);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
    $content = curl_exec($ch);

    // проверяем что вернул curl_exec()
    if ($content === false) {
        throw new Exception(curl_error($ch), curl_errno($ch));
    }

    //получаем контент тэга <head>
    preg_match("/<head>(.*?)<\/head>/s", $content, $match_head);

    //получаем контент тэга <body>
    preg_match("/<body(.*?)>(.*?)<\/body>/s", $content, $match_body);
    $page_html = $match_body[2];

    //получаем ссылку на скрипт
    preg_match("/<script(.*?)><\/script>/s", $content, $match_script);
    $page_script = "<script".$match_script[1]."></script>";

    if (strlen($page_html)>0) {
        //получаем "чистый" текст убрав все тэги
        $page_text = strip_tags($page_html);
        //ищем все вхождения слов из шести букв
        preg_match_all('/\b\w{6}\b/ui', $page_text, $match_six);
        //убираем дубликаты из полученного массива
        $match_six_ar = array_unique($match_six[0]);

        //заменяем в html найденные слова
        $page_html_tm =  preg_replace_callback(
            '/\b\w{6}\b/ui',
            function ($m_six) use($match_six_ar){ //если слово из 6-ти букв входит в ранее отобранный массив, добавляем ТМ
                return (in_array($m_six[0],$match_six_ar, true)) ? $m_six[0] . "™" : $m_six[0];
            },
            $page_html
        );

        //заменяем в body все ссылки к картинкам на абсолютные
        $page_html_new = preg_replace('/<img(\s.*?)src=[\'"](.*?)[\'"](\s.*?)>/is','<img$1src="https://news.ycombinator.com/$2"$3>',$page_html_tm);
        //заменяем ссылку к скриптам на абсолютную
        $page_script = preg_replace('/<script(\s.*?)src=[\'"](.*?)[\'"](.*?)><\/script>/is','<script$1src="https://news.ycombinator.com/$2"$3></script>',$page_script);
        //заменяем в head все ссылки на абсолютные
        $page_head_new = preg_replace('/<link(\s.*?)href=[\'"](.*?)[\'"](.*?)>/is','<link$1href="https://news.ycombinator.com/$2"$3>',$match_head[1]);

        //возвращаем для отображения страницу
        print_r("<html lang='en'' op='news''>");
        print_r("<head>".$page_head_new."</head>");
        print_r("<body".$match_body[1].">".$page_html_new."</body>");
        print_r($page_script);
        print_r("</html>");
    }

} catch(Exception $e) {

    trigger_error(sprintf(
        'Curl failed with error #%d: %s',
        $e->getCode(), $e->getMessage()),
        E_USER_ERROR);

} finally {
    // Close curl handle unless it failed to initialize
    if (is_resource($ch)) {
        curl_close($ch);
    }
}

