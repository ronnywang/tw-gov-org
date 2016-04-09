<?php

$doc = new DOMDocument;
@$doc->loadXML(file_Get_contents($_SERVER['argv'][1]));

$units = new StdClass;
foreach ($doc->getElementsByTagName('法規') as $law_dom) {
    $name = $law_dom->getElementsByTagName('法規名稱')->item(0)->nodeValue;
    if (!strpos($name, '組織法') and !strpos($name, '組織條例')) {
        continue;
    }

    $law_type = $law_dom->getElementsByTagName('法規類別')->item(0)->nodeValue;
    if (strpos($law_type, '停止適用') or strpos($law_type, '廢止')) {
        continue;
    }
    preg_match('#^(.*)組織#', $name, $matches);
    $ret = new StdClass;
    $unit = str_replace('中華民國', '', $matches[1]);
    $unit = str_replace('臺', '台', $unit);
    $ret->{'單位'} = $unit;

    if (in_array($unit, array(
        '行政院主計處',
        '行政院主計處電子處理資料中心',
    ))) {
    continue;
    }
    $match_unit = "({$unit}|本會|本局得|本局)";

    foreach ($law_dom->getElementsByTagName('條文') as $rule_dom) {
        $no = $rule_dom->getElementsByTagName('條號')->item(0)->nodeValue;
        $content = trim($rule_dom->getElementsByTagName('條文內容')->item(0)->nodeValue);
        $content = str_replace('(', '（', $content);
        $content = str_replace(')', '）', $content);
        $content = str_replace('臺', '台', $content);
        $content = str_replace('︰', '：', $content);
        $content = preg_replace('#\s+#', '', $content);

        if (preg_match('#([^，]+)為(.*)，特設([^（]*)(（以下簡稱(.*)）)?(，為相當.*)?。#u', preg_replace('#\s+#', '', $content), $matches)) {
            $ret->{'母單位'} = $matches[1];
            $ret->{'成立目的'} = $matches[2];
            $match_unit = "({$unit}|{$matches[5]})";
        } elseif (preg_match('#本法依(.*)制定之#', $content, $matches)) {
            $ret->{'法源依據'} = $matches[1];
        } elseif (strpos($content, '行使憲法所賦予之職權') or strpos($content, '依據憲法行使職權')) {
            $ret->{'法源位階'} = '憲法';
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
        } elseif (preg_match('#交通部設(.*)等(.*)港務局#', $content, $matches)) {
            foreach (explode('、', $matches[1]) as $locate) {
                $ret->{'子單位'}[] = $locate . '港務局';
            }
        } elseif (strpos($content, '設下列') or strpos($content, '設左列')) {
            $content = explode('：', $content, 2)[1];
            $lines = split("。", $content);

            foreach ($lines as $line) {
                if (strpos($line, '、')) {
                    $ret->{'子單位'}[] = explode('。', explode('、', $line)[1])[0];
                    continue;
                }
            }
        } elseif (preg_match("#^{$match_unit}(因應業務需要，得)?設([^。，]+[^人])[。，]#u", trim(preg_replace('#\s+#', '', $content)), $matches)) {
            $matches[3] = explode('；', $matches[3])[0];
            foreach (preg_split('#[及、]#u', $matches[3]) as $sub_unit) {
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
                        if ($matches[2]) {
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
                    } elseif (strpos($term, '特任') !== false or strpos($term, '簡任') !== false or strpos($term, '薦任') !== false or strpos($term, '委任') !== false or strpos($term, '專任') !== false) {
                        $ret2->{'任命方式'}[] = $term;
                        continue;
                    } else {
                        $ret2->{'事項'}[] = $term;
                    }
                }
                if (!property_exists($ret2, '職稱')) {
                    // TODO
                    continue;
                }
                $ret->{'人事'}[] = $ret2;
            }
        } elseif (strpos($content, '掌理下列事項：')) {
            if (preg_match('#\s*（以下簡稱(.*)）\s*#', $content, $matches)) {
                $match_unit = "({$unit}|{$matches[1]})";
                $content = str_replace($matches[0], '', $content);
            }
            $lines = split("。", $content);
            $ret2 = new StdClass;
            $ret2->{'掌理事項'} = array();
            foreach ($lines as $line) {
                if (preg_match('#^(.*)掌理下列事項#', $line, $matches)) {
                    $ret2->{'單位'} = str_replace('，', '', $matches[1]);
                    continue;
                }

                if (strpos($line, '、')) {
                    $ret2->{'掌理事項'}[] = explode('。', explode('、', $line, 2)[1])[0];
                    continue;
                }
                continue;
            }
            if (preg_match('#^' . $match_unit . '$#', $ret2->{'單位'})) {
                $ret->{'掌理事項'} = $ret2->{'掌理事項'};
            } else {
                if (!property_exists($ret, '子單位掌理')) {
                    $ret->{'子單位掌理'} = new StdClass;
                }
                if (property_exists($ret2, '單位')) {
                    $ret->{'子單位掌理'}->{$ret2->{'單位'}} = $ret2->{'掌理事項'};
                }
            }
        } elseif (strpos($content, '隸屬於' . $unit)) {
            // 中央研究院、國史館、國父陵園管理委員會隸屬於總統府，其組織均另以法律定之
            preg_match('#^(.*)隸屬於#', $content, $matches);
            foreach (explode('、', $matches[1]) as $n) {
                $ret->{'子單位'}[] = $n;
            }

        } elseif (property_exists($ret, '子單位') and preg_match('#^(.*)掌理(.*)。#', $content, $matches) and in_array($matches[1], $ret->{'子單位'})) {
            $ret->{'子單位掌理'}->{$matches[1]} = array($matches[2]);
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
