<?php

include_once ('config.php');
include_once ('functions.php');

$q = $_GET ["q"];
$l = $_GET ["l"];
if ($q != "" and strlen($q) > 2) {

    if ($l == "ger") {
        $request_url = SOLR_URL . "/select?q=";
        $request_url .= "german:" . urlencode($q)
                . "&wt=xml";
        $response = file_get_contents($request_url);
        $xml = simplexml_load_string($response);

        $title = xpath($xml, "/response/result/doc/arr[@name='title']/str/text()");
        if ($title != "") {
            $q = strtolower($title);
        }
    }

    $response = file_get_contents("http://preview.ncbi.nlm.nih.gov/portal/utils/autocomp.fcgi?dict=pm_related_queries_2&callback=?&q=" . urlencode($q));
    preg_match("/Array\(([^\)]+)/", $response, $response);
    $response = $response [1];
    $response = trim($response, '"');
    $response = explode('", "', $response);
    $response = array_slice($response, 0, 5);

    /*
     * Generate Mock data which can be used for debugging/testing sleep(1); $response = array("test" . rand(0, 1000), "test". rand(0, 1000), "test" . rand(0, 1000), "test". rand(0, 1000));
     */

    print json_encode($response);
}
?>