<?php

/**
 * Recursive function returns directory tree listing.
 *
 * @param $path
 * @param $allowed_filenames
 * @param int $level
 *
 * @return string
 */
function getDirectory($path, $allowed_filenames, $level = 0)
{
    // Directories/files to ignore when listing output.
    $ignore = array( 'cgi-bin', '.', '..' );

    // Need to know the base URL...
    $pathsplit = explode('/', $path);
    $pathcount = count($pathsplit);
    $base = implode('/', array_slice($pathsplit, $pathcount-$level));
    if ($base !== '') {
        $base .= '/';
    }

    $retval = '';

    $dh = @opendir($path);
    while (false !== ( $file = readdir($dh) )) {
        if (!in_array($file, $ignore)) {
            if (is_dir("$path/$file")) {
                $retval .= str_repeat(' ', ( $level * 4 )); // indenting
                $retval .= "<strong>$file</strong>\n";
                $retval .= getDirectory("$path/$file", $allowed_filenames, ($level+1));
            } else {
                if (preg_match($allowed_filenames, $file, $matches)) {
                    $retval .= str_repeat(' ', ( $level * 4 )); // indenting
                    $retval .= "<a href=\"?file=" . urlencode("$base$file") . "\">$file</a>\n";
                }
            }
        }
    }
    closedir($dh);

    return $retval;
}


# Adapted from https://stackoverflow.com/a/19294311/9100
function build_table($array){
    # start table
    $html = '<table>';

    # header row
    $html .= '<tr>';
    foreach($array as $key=>$value){
	$html .= '<th>' . htmlspecialchars($key) . '</th>';
    }
    $html .= '</tr>';

    # data row
    $html .= '<tr>';
    foreach($array as $key=>$value){
        $html .= '<td>' . htmlspecialchars($value) . '</td>';
    }
    $html .= '</tr>';

    # finish table and return it
    $html .= '</table>';
    return $html;
}


function showGrades($ruser, $csvFile)
{
    # Show lines from the csvFile where cwlid=$ruser
    $retVal = "";
    try {
        $csv = array_map("str_getcsv", file($csvFile, FILE_SKIP_EMPTY_LINES));
        $keys = array_shift($csv);
        foreach ($csv as $i=>$row) {
            $csv[$i] = array_combine($keys, $row);
            if (!empty($csv[$i]['cwlid']) && $csv[$i]['cwlid'] === $ruser) {
                # $retVal .= json_encode($csv[$i]) . "\n";
                $retVal .= build_table($csv[$i]) . "\n";
            }
        }
    } catch (ValueError $e) {
        error_log('There was a problem processing ' . $csvFile . " - " . $e->getMessage());
        $retVal .= "There was a problem processing the grades file!\n";
    }
    return $retVal;
}

$ruser = $_SERVER['REMOTE_USER'];
if (!$ruser) {
    echo "No user id!";
    exit;
}
# Case insensitive authentication probably works... Force lower case.
$ruser = strtolower($ruser);
# Pattern match $ruser here, just in case...
if (! preg_match("/^[a-zA-Z0-9][-a-zA-Z0-9]*[a-zA-Z0-9]$/", $ruser)) {
    echo "Bad user id: $ruser";
    exit;
}

$course      = 'csNNN';
$handbackDir = '/home/c/csNNN/public_html/handback/deliverThis';
$heading     = "<h2>Download $course files + grades for $ruser</h2>";
$subheading  = "Files for you:<br>";
$gradesCSV   = 'grades.csv';
$gsubheading = "Grades for you:<br>";

# don't allow periods in directory names. Prevents hacking using ../
# Also, 'wall in' userid with a '%' character.
$allowed_filenames = "/^[a-zA-Z0-9]+[-\/_a-zA-Z0-9]*[-%_\.a-zA-Z0-9]*%".$ruser."(?:%[-_\.a-zA-Z0-9]*)*\.(pdf|html)$/";

# Put overrides of above parameters in a separate file.
if (file_exists('handback.cfg')) {
    include 'handback.cfg';
}

$errors = array();
$htmlout = '';
if (is_dir($handbackDir) && is_readable($handbackDir)) {
    if (isset($_GET['file'])) {
        if (preg_match($allowed_filenames, $_GET['file'], $matches)) {
            $file = htmlspecialchars($matches[0], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            $htmlout .= "<p>File specified: " . $file . "</p>\n";
            $handbackDir = $handbackDir . '/' . $file;
            if (is_file($handbackDir)) {
                $htmlout .= "File exists: " . $handbackDir;
                if ($fd = fopen($handbackDir, "r")) {
                    $fsize = filesize($handbackDir);
                    $path_parts = pathinfo($handbackDir);
                    if ($path_parts["extension"] == 'html') {
                        header("Content-type: text/html");
                    } else {
                        header("Content-type: application/octet-stream");
                    }
                    header("Content-disposition: filename=\"".$path_parts["basename"]."\"");
                    header("Content-length: $fsize");
                    header("Cache-control: private");
                    while (!feof($fd)) {
                        $buffer = fread($fd, 2048);
                        echo $buffer;
                    }
                    fclose($fd);
                    exit;
                } else {
                    $htmlout .= "Couldn't open file: " . $handbackDir;
                }
            } else {
                $htmlout .= "No such file: " . $handbackDir;
            }
        } else {
            $htmlout .= "Bad file parameter\n";
        }
    } else {
        $htmlout .= "$subheading\n";
        $htmlout .= "<blockquote><pre>\n";
        $htmlout .= getDirectory($handbackDir, $allowed_filenames);
        $htmlout .= "</pre></blockquote>\n";
	if (file_exists($gradesCSV)) {
            $htmlout .= "$gsubheading\n";
            $htmlout .= "<blockquote><pre>\n";
            $htmlout .= showGrades($ruser, $gradesCSV);
            $htmlout .= "</pre></blockquote>\n";
	}
    }
} else {
    $htmlout .= "There is a problem with the handback dir... ";
}
?>
<!DOCTYPE html>
<html>
  <head>
    <meta charset="utf-8">
    <title>Handback</title>
    <!--Stylesheet courtesy of https://github.com/mdn/learning-area/blob/master/html/tables/basic/minimal-table.css-->
    <style>
html {
  font-family: sans-serif;
}

table {
  border-collapse: collapse;
  border: 2px solid rgb(200,200,200);
  letter-spacing: 1px;
  font-size: 0.8rem;
}

td, th {
  border: 1px solid rgb(190,190,190);
  padding: 10px 20px;
}

th {
  background-color: rgb(235,235,235);
}

td {
  text-align: center;
}

tr:nth-child(even) td {
  background-color: rgb(250,250,250);
}

tr:nth-child(odd) td {
  background-color: rgb(245,245,245);
}

caption {
  padding: 10px;
}
    </style>
  </head>
<body>
<?php
print $heading;
print $htmlout;

#print_r("<br>$handbackDir<br>\n");
#print_r("<br><pre>");
#var_dump($GLOBALS);
#var_dump($_SERVER);
#print_r("</pre>");
?>
</body>
</html>
