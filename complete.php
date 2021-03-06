<?php
/************************************************************************
 * Geonames autocomplete remote JSON data souce for Twitter typeahead.js
 *
 * Copyright (c) 2014, Michael J. Radwin
 * All rights reserved.
 *
 * Redistribution and use in source and binary forms, with or without modification,
 * are permitted provided that the following conditions are met:
 *
 * - Redistributions of source code must retain the above copyright notice, this
 *   list of conditions and the following disclaimer.
 *
 * - Redistributions in binary form must reproduce the above copyright notice, this
 *   list of conditions and the following disclaimer in the documentation and/or
 *   other materials provided with the distribution.
 *
 * THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS "AS IS" AND
 * ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE IMPLIED
 * WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE ARE
 * DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT HOLDER OR CONTRIBUTORS BE LIABLE FOR
 * ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES
 * (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES;
 * LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND ON
 * ANY THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT
 * (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE OF THIS
 * SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.
 ***********************************************************************/

if (isset($_SERVER["HTTP_IF_MODIFIED_SINCE"])) {
    header("HTTP/1.1 304 Not Modified");
    header("Content-Type: application/json; charset=UTF-8");
    echo json_encode(array("status" => "Not Modified")), "\n";
    exit;
}

$qraw = isset($_REQUEST["q"]) ? trim($_REQUEST["q"]) : "";
if (strlen($qraw) == 0) {
    header("HTTP/1.1 404 Not Found");
    header("Content-Type: application/json; charset=UTF-8");
    echo json_encode(array("error" => "Not Found")), "\n";
    exit;
}

$file = $_SERVER["DOCUMENT_ROOT"] . "/geonames.sqlite3";
$db = new SQLite3($file);
if (!$db) {
    error_log("Could not open SQLite3 $file");
    die();
}

$qe = SQLite3::escapeString($qraw);

$sql = <<<EOD
SELECT geonameid,
asciiname, admin1, country,
population, latitude, longitude, timezone
FROM geoname_fulltext
WHERE longname MATCH '"$qe*"'
ORDER BY population DESC
LIMIT 10
EOD;

$query = $db->query($sql);
if (!$query) {
    error_log("Querying '$qe' from $file: " . $db->lastErrorMsg());
    die();
}

$search_results = array();

while ($res = $query->fetchArray(SQLITE3_ASSOC)) {
      $loc = array("id" => $res["geonameid"],
                  "asciiname" => $res["asciiname"],
                  "latitude" => $res["latitude"],
                  "longitude" => $res["longitude"],
                  "timezone" => $res["timezone"],
                  "population" => $res["population"],
                  "geo" => "geoname");
      $longname = $res["asciiname"];
      $a1tokens = array();
      if (!empty($res["admin1"])) {
          $loc["admin1"] = $res["admin1"];
          if (strncmp($res["admin1"], $res["asciiname"], strlen($res["asciiname"])) != 0) {
              $longname .= ", " . $res["admin1"];
              $a1tokens = explode(" ", $res["admin1"]);
          }
      }
      $ctokens = array();
      if (!empty($res["country"])) {
          $loc["country"] = $res["country"];
          $longname .= ", " . $res["country"];
          $ctokens = explode(" ", $res["country"]);
      }
      $loc["value"] = $longname;
      $tokens = array_merge(explode(" ", $res["asciiname"]),
        $a1tokens, $ctokens);
      $loc["tokens"] = $tokens;
      $search_results[] = $loc;
}

// clean up
unset($query);
$db->close();
unset($db);

if (count($search_results) == 0) {
    header("HTTP/1.1 404 Not Found");
    header("Content-Type: application/json; charset=UTF-8");
    echo json_encode(array("error" => "Not Found")), "\n";
} else {
    header("Content-Type: application/json; charset=UTF-8");
    header("Access-Control-Allow-Origin: *");
    echo json_encode($search_results, JSON_NUMERIC_CHECK);
}

?>
