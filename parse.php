<?php

$doc = new DOMDocument;
@$doc->loadXML(file_Get_contents($_SERVER['argv'][1]));

$units = new StdClass;
foreach ($doc->getElementsByTagName('法規') as $law_dom) {
    $name = $law_dom->getElementsByTagName('法規名稱')->item(0)->nodeValue;
    if (!strpos($name, '組織法') and !strpos($name, '組織條例')) {
        continue;
    }

    if (strpos($law_dom->getElementsByTagName('法規類別')->item(0)->nodeValue, '停止適用')) {
        continue;
    }
    $match_unit = "({$unit}|本會)";
    preg_match('#^(.*)組織#', $name, $matches);
    $ret = new StdClass;
    $ret->{'單位'} = $unit = str_replace('中華民國', '', $matches[1]);

    foreach ($law_dom->getElementsByTagName('條文') as $rule_dom) {
        $no = $rule_dom->getElementsByTagName('條號')->item(0)->nodeValue;
        $content = trim($rule_dom->getElementsByTagName('條文內容')->item(0)->nodeValue);
        if (preg_match('#本法依(.*)制定之#', $content, $matches)) {
            $ret->{'法源依據'} = $matches[1];
        } elseif (strpos($content, '行使憲法所賦予之職權') or strpos($content, '依據憲法行使職權')) {
            $ret->{'法源位階'} = '憲法';
        } elseif (strpos($content, '職系')) {
            //tODO
        } elseif (strpos($content, '本部之次級機關及其業務如下') !== false) {
            $list = explode('本部之次級機關及其業務如下：', $content)[1];
            $lines = split("。", preg_replace("#\s+#", '', $list));
            foreach ($lines as $line) {
                if (trim($line) == '') {
                    continue;
                }
                if (preg_match('#.*、(.*)：(.*)$#u', $line, $matches)) {
                    $ret->{'子單位'}[] = $matches[1];
                    if (!$ret->{'子單位掌理'}) {
                        $ret->{'子單位掌理'} = new StdClass;
                    }
                    $ret->{'子單位掌理'}->{$matches[1]} = array($matches[2]);
                }
            }
        } elseif (strpos($content, '設下列')) {
            $lines = split("\n", $content);
            foreach ($lines as $line) {
                if (strpos($line, '設下列')) {
                    continue;
                }
                if (strpos($line, '、')) {
                    $ret->{'子單位'}[] = explode('。', explode('、', $line)[1])[0];
                    continue;
                }
                continue;
                echo 'line';
                print_r($line);
                exit;
            }
        } elseif (preg_match('#^' . $match_unit . '設([^。]+[^人])。$#u', trim($content), $matches)) {
            foreach (explode('及', $matches[2]) as $sub_unit) {
                $ret->{'子單位'}[] = $sub_unit;
            }
        } elseif (preg_match('#置(.*)[一二三四五六七八九十]人#u', $content)) {
            $lines = preg_split("#[。；]#u", str_replace("\n", "", $content));
            foreach ($lines as $line) {
                if (trim($line) == '') {
                    continue;
                }
                $ret2 = new StdClass;
                foreach (explode('，', $line) as $term) {
                    if (preg_match('#^' . $match_unit . '?(.*)置(.*?)(([一二三四五六七八九十]+人至)?[一二三四五六七八九十]+人)$#u', $term, $matches)) {
                        $ret2->{'職稱'} = $matches[3];
                        if ($matches[1]) {
                            $ret2->{'種類'} = $matches[2];
                        }
                        $ret2->{'人數'} = $matches[4];
                        continue;
                    } elseif (preg_match('#^' . $match_unit . '設(.*[^人])$#u', $term, $matches)) {
                        $ret2->{'單位'} = $matches[2];
                        $ret->{'子單位'}[] = $matches[2];
                    } elseif (preg_match('#^(.*?)(([一二三四五六七八九十]+人至)?[一二三四五六七八九十]+人)$#u', $term, $matches)) {
                        $ret2->{'職稱'} = $matches[1];
                        $ret2->{'人數'} = $matches[2];
                    } elseif (strpos($term, '特任') !== false or strpos($term, '簡任') !== false or strpos($term, '薦任') !== false or strpos($term, '委任') !== false) {
                        $ret2->{'任命方式'}[] = $term;
                        continue;
                    } else {
                        $ret2->{'事項'}[] = $term;
                    }
                }
                if (!$ret2->{'職稱'}) {
                    // TODO
                    continue;
                }
                $ret->{'人事'}[] = $ret2;
            }
        } elseif (preg_match('#^(.*)掌理(.*)。#', $content, $matches) and in_array($matches[1], $ret->{'子單位'})) {
            $ret->{'子單位掌理'}->{$matches[1]} = array($matches[2]);
        } elseif (strpos($content, '掌理下列事項：')) {
            continue;
            $lines = split("\n", $content);
            $ret2 = new StdClass;
            $ret2->{'掌理事項'} = array();
            foreach ($lines as $line) {
                if (preg_match('#^(.*)掌理下列事項#', $line, $matches)) {
                    $ret2->{'單位'} = $matches[1];
                    continue;
                }

                if (strpos($line, '、')) {
                    $ret2->{'掌理事項'}[] = explode('。', explode('、', $line)[1])[0];
                    continue;
                }
                echo 'line';
                print_r($line);
                exit;
            }
            $ret->{'子單位掌理'}->{$ret2->{'單位'}} = $ret2->{'掌理事項'};
        } elseif (preg_match('#以依法選出之(.*)組織之#', $content, $matches)) {
            $ret->{'人員進入方式'} = '依法選出';
            $ret->{'人員種類'} = $matches[1];
        } elseif (strpos($content, '誓詞')) {
            $ret->{'誓詞'} = $content;
        } else {
            continue;
            print_r($ret);
            print_r($no);
            print_r($content);
            exit;
        }
    }
    if (!json_encode($ret)) {
        var_dump($ret);
        exit;
    }
    $units->{$unit} = $ret;
}
echo json_encode($units, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
$error = json_last_error();
var_dump($error);
