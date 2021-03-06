<?php

require_once('./lib/chunk/class.chunk.php');
require_once('./lib/wiky_wikipedia_parser/wiky.inc.php');
require_once('./lib/http_post/http_post.php');

include('./config.php');


$mediawiki_converter = new wiky();

$start = microtime(true);
$processed_entries = 0;
$successfully_processed_entries = 0;

$credibility = array();
if (($handle = fopen('./wikipedia/relevant_articles_credibility.txt', 'r')) !== FALSE) {
    while (($row = fgetcsv($handle, 1000, ';')) !== FALSE) {
        $entry = array($row[1]);
        $credibility[str_replace("_", " ", $row[0])] = $entry;
    }
    fclose($handle);
}

// Iterate through XML files in the directory
$handle = opendir('./wikipedia');
while (false !== ($file = readdir($handle))) {
    $extension = strtolower(substr(strrchr($file, '.'), 1));
    echo $extension . "\n";
    if ($extension == "xml") {

        $file = new Chunk($file, array('element' => 'page', 'path' => './wikipedia/', 'chunkSize' => 3000));

        while ($xml_string = $file->read()) {
            $processed_entries++;
            // print mb_detect_encoding($xml);

            try {
                $article = new SimpleXMLElement($xml_string);
            } catch (Exception $e) {
                echo 'Caught exception: ', $e->getMessage(), "\n";
                continue;
            }

            // Fetch article title
            $article_title = $article->title;
            echo $article_title . "\n";
            $url_id = urlencode(str_replace(" ", "_", $article_title));

            $entry = $credibility[(string) $article_title];

            $suspicious = true;

            switch ($entry[0]) {
                case "FA":
                case "GA":
                case "B":
                case "C":
                case "Start":
                    $suspicious = false;
                    break;
            }

            // Check if this is actually a redirect
            $redirect = $article->redirect['title'];
            if ($redirect != "") {
                print "Skipping $article_title because it is a redirect to $redirect \n";
                continue;
            }

            // Fetch Wikipedia article code
            $wikipedia_code = $article->revision->text;
            if ($wikipedia_code == "")
                continue;


            // Get raw text (convert to HTML, then strip HTML tags)
            //$article_text_without_wiki_markup = strip_tags($mediawiki_converter->parse($wikipedia_code));
            $article_text_without_wiki_markup = trim(strip_tags($mediawiki_converter->parse($wikipedia_code)));

            // Extract the first paragraph (to be used as the 'key assertion')
            preg_match("/^\s*(.+)[\r\n]/", $article_text_without_wiki_markup, $matches);
            $key_assertion = $matches[1];

            // Replace abbreviations with expanded forms
            // Currently disabled because it does not work with (long) Wikipedia article code
            /*
              $abbreviation_expander_output = trim(shell_exec('java ExtractAbbrev "' . addslashes($article_text_without_wiki_markup) . '"'));
              $abbreviation_expander_output_lines = explode("\n", $abbreviation_expander_output);
              foreach ($abbreviation_expander_output_lines as $line) {
              if (trim($line) == "") continue;
              $abbreviation_mapping = explode("\t", $line);
              $wikipedia_code = str_replace(" " . $abbreviation_mapping[0], " " . $abbreviation_mapping[1], $article_text_without_wiki_markup);
              }
             */

            // Fetch timestamp (represent it as "date created")
            // $output_file = "wikipedia_solr_xml_output/wikipedia_" . $url_id. ".xml";

            $output = "<?xml version=\"1.0\" encoding=\"UTF-8\"?><update><add><doc>\n";
            $output .= "<field name='title'>" . htmlspecialchars($article_title) . "</field>\n";
            $output .= "<field name='key_assertion'>" . htmlspecialchars($key_assertion) . "</field>\n";
            $output .= "<field name='body'>" . htmlspecialchars($article_text_without_wiki_markup) . "</field>\n";
            $output .= "<field name='data_source_name'>Wikipedia</field>\n";
            $output .= "<field name='dateCreated'>" . $article->revision->timestamp . "</field>\n"; // TODO: perhaps replace with last edit of wikipedia page
            $output .= "<field name='id'>http://en.wikipedia.org/wiki/" . $url_id . "</field>\n";
            $output .= "<field name='mimeType'>text/plain</field>\n";
            $output .= "<field name='category'>Wikipedia</field>\n";
            $output .= "<field name='dataset_priority'>8</field>\n";
            if ($suspicious) {
                $output .= "<field name='suspicious'>t</field>\n";
            } else {
                $output .= "<field name='suspicious'>f</field>\n";
            }
            $output .= "</doc></add></update>";

            do_post_request(SOLR_URL . '/update', $output);

            $successfully_processed_entries++;
            print($successfully_processed_entries . "\n");
        }
    }
}

print do_post_request(SOLR_URL . '/update', "<?xml version=\"1.0\" encoding=\"UTF-8\"?><update><commit/><optimize/></update>");

print "\n Processed $successfully_processed_entries out of $processed_entries records successfully. ";
$end = (microtime(true) - $start);
print "Completed in {$end} seconds.";