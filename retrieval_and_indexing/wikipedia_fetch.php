<?php

// Load list of article labels into an array
$filename = "relevant_articles_credibility.txt";
$article_list_file_contents = file_get_contents("./wikipedia/" . $filename);
$article_labels = array();

if (($handle = fopen('./wikipedia/' . $filename, 'r')) !== FALSE) {
    while (($row = fgetcsv($handle, 1000, ';')) !== FALSE) {
        $article_labels[] = str_replace("_", " ", $row[0]);
    }
    fclose($handle);
}

$count = count($article_labels);
print "$count articles found\n";

$retmax = 1000; // Maximum number of entries returned per request

for ($retstart = 0; $retstart < $count; $retstart += $retmax) {
    $pages = "";
    if ($retstart + $retmax < $count) {
        for ($number = $retstart; $number < $retstart + $retmax; $number++) {
            $pages.= urlencode($article_labels[$number]) . "%0D%0A";
        }
        //last part
    } else {
        for ($number = $retstart; $number < $count; $number++) {
            $pages.= urlencode($article_labels[$number]) . "%0D%0A";
        }
    }

    $url = 'http://en.wikipedia.org/w/index.php?title=Special:Export&action=submit';

    $options = array('http' => array('method' => 'POST', 'content' => 'pages=' . $pages . '&curonly=1'));
    $context = stream_context_create($options);
    $result = file_get_contents($url, false, $context);

    file_put_contents("./wikipedia/" . $retstart . ".xml", $result);

    echo $retstart . "\n";
}    