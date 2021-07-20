<?php
/*PhpDoc:
name: json.php
title: json.php - affiche le résultat d'une requête Http en positionnant le paramètre Accept à JSON ou JSON-LD
doc: |
  effectue une requête Http en positionnant le paramètre Accept
  soit à 'application/ld+json' soit à 'application/json,application/geo+json'
  Affiche le résultat en Yaml en remplacant les URL par des liens
journal: |
  20/7/2021:
    - correction des types MIME reconnus pour JSON et HTML
      - vu la variété du format de l'encodage, il faudrait probablement utiliser un preg_match()
  4/2/2021:
    - ajout de l'option http ['ignore_errors' => '1'] pour éviter une erreur de lecture lors d'une erreur Http
  21/1/2021:
    - transfert de comhisto dans geoapi/tools
    - améliorations
  28/11/2020:
    - améliorations
  26/11/2020:
    - changement de nom
  21/11/2020:
    - améliorations
*/
//ini_set('max_execution_time', 30*60);

require_once __DIR__.'/../vendor/autoload.php';

use Symfony\Component\Yaml\Yaml;
use Symfony\Component\Yaml\Exception\ParseException;

// types MIME reconnus pour JSON et HTML dans le contenu retourné
define('CONTENT_TYPES', [
  'json'=> [
    'application/ld+json',
    'application/ld+json; charset="utf-8"',
    'application/json',
    'application/json; charset="utf-8"',
    'application/vnd.oai.openapi+json;version=3.0',
  ],
  'geojson'=> ['application/geo+json', 'application/geo+json; charset="utf8"'],
  'html'=> ['text/html','text/html; charset=UTF-8','text/html; charset="UTF-8"'],
]
);

function replaceUrl(string $text): string {
  if (strlen($text) > 1e6) return $text; // Si le text est trop long (1 Mo) on ne fait rien car trop long
  $args = "&ydepth=".($_GET['ydepth'] ?? 9).(isset($_GET['ld']) ? "&ld=$_GET[ld]" : '');
  static $pattern = '!(http(s)?:)(//[^ \n\'"]*)!';
  while (preg_match($pattern, $text, $m)) {
    $text = preg_replace($pattern, "<a href='?url=".urlencode($m[1].$m[3])."$args'>Http$m[2]:$m[3]</a>", $text, 1);
    //break;
  }
  return $text;
}

// transforme le contenu de $http_response_header en un array
function response_header(array $input): array {
  if (!$input) return ['error'=> '$http_response_header non défini'];
  $output = ['returnCode'=> array_shift($input)];
  foreach ($input as $val) {
    $pos = strpos($val, ': ');
    $output[substr($val, 0, $pos)] = substr($val, $pos+2);
  }
  return $output;
}

function outputJsonError(string $contents) {
  static $errorLabels = [
    JSON_ERROR_NONE => 'Aucune erreur',
    JSON_ERROR_DEPTH => 'Profondeur maximale atteinte',
    JSON_ERROR_STATE_MISMATCH => 'Inadéquation des modes ou underflow',
    JSON_ERROR_CTRL_CHAR => 'Erreur lors du contrôle des caractères',
    JSON_ERROR_SYNTAX => 'Erreur de syntaxe ; JSON malformé',
    JSON_ERROR_UTF8 => 'Caractères UTF-8 malformés, probablement une erreur d\'encodage',
  ];
  
  $error = json_last_error();
  echo '<pre>',Yaml::dump([
    'json_decode_error'=> [
      'code'=> $error,
      'label'=> $errorLabels[$error] ?? 'Erreur inconnue',
    ],
    'body'=> $contents
  ], 2, 2, Yaml::DUMP_MULTI_LINE_LITERAL_BLOCK);
  die();
}

if (($action = $_GET['action'] ?? null) == 'test') { // code de test des erreurs 
  if (1) { // JSON malformé 
    header('Content-type: application/json; charset="utf8"');
    die("Test\nligne\n");
  }
}

$url = $_GET['url'] ?? '';
$ydepth = $_GET['ydepth'] ?? 9;

echo "<!DOCTYPE HTML><html>\n<head><meta charset='UTF-8'><title>json</title>\n</head><body>\n";
// Documentation
if (($action == 'doc') || !$url) {
  echo "L'objectif de ce script est de visualiser facilement un document JSON produit par une API.<br>\n
    Pour cela, il effectue une requête Http Get sur l'URL fourni dans le formulaire ci-dessous
    en positionnant le paramètre Http Accept soit à 'application/ld+json' soit à 'application/json,application/geo+json'
    en fonction de la coche du formulaire.<br>
    
    Il affiche d'une part le header du résultat et d'autre part :<ul>
      <li>si le type MIME est un des types Json/GeoJSON ci-dessous et que le décodage Json est ok
        alors affiche le document en Yaml en remplacant les URL par des liens cliquables.</li>
      <li>si le type MIME est un des types Json/GeoJSON ci-dessous et que le décodage JSON génère une erreur
        alors affiche un message d'erreur ainsi que le document comme texte.</li>
      <li>si type du header est un des types Html ci-dessous alors affiche le document brut.</li>
      <li>si type du header n'est aucun des types Json ou Html alors affiche le document comme texte.</li>
    </ul>
    De plus, s'il s'agit d'un document GeoJSON alors propose sa visualisation sur http://geojson.io/
  ";
  echo "</p>Les types MIMES reconnus sont:",
  '<pre>',Yaml::dump(['typesMimes'=> CONTENT_TYPES], 3, 2),"</pre>\n";
  if (!$url)
    echo "Pour supprimer cette doc, fournir une URL.\n";
  else
    echo "Pour supprimer cette doc, clicker à nouveau sur le lien doc.\n";
}

echo "<table><tr>" // le formulaire
      . "<td><table border=1><form><tr>"
      . "<td><input type='text' size=130 name='url' value='$url'/></td>\n"
      . "<td><label for='ld'>ld</label><input type='checkbox' id='ld' name='ld' value='on'"
        .(isset($_GET['ld']) ? ' checked' : '')."></td>"
      . "<td><input type='text' size=3 name='ydepth' value='$ydepth'/></td>\n"
      . "<td><input type='submit' value='Get'></td>\n"
      . "</tr></form></table></td>\n"
      . "<td>(<a href='?".(($action<>'doc') ? 'action=doc&amp;' : '')."url=".urlencode($url)."'>doc</a>)</td>\n"
      . "</tr></table>\n";

if (!$url)
  die("Url vide");

$accept = isset($_GET['ld']) ? 'application/ld+json' : 'application/json,application/geo+json';
//echo (!isset($_GET['ld']) ? "ld non défini" : "ld=$_GET[ld]"),"<br>\n";
$opts = [
  'http'=> [
    'method'=> 'GET',
    'ignore_errors' => '1',
    'header'=> "Accept: $accept\r\n"
              ."Accept-language: en\r\n"
              ."Cookie: foo=bar\r\n",
  ],
];
//echo "url=$url\n";
if (FALSE === $contents = @file_get_contents($url, false, stream_context_create($opts))) {
  echo "<pre>Erreur de lecture de $url\nhttp_response_header = ";
  die(Yaml::dump(['header'=> response_header($http_response_header ?? [])], 3, 2));
}
$response_header = response_header($http_response_header ?? []);
echo "<pre>",Yaml::dump(['header'=> $response_header], 3, 2),"</pre>\n";

if (($response_header['Content-Encoding'] ?? null) == 'gzip') {
  $contents = gzdecode($contents);
}

$contentType = $response_header['Content-Type'] ?? null;
if (in_array($contentType, CONTENT_TYPES['html']))
  die($contents);
if (!in_array($contentType, array_merge(CONTENT_TYPES['json'], CONTENT_TYPES['geojson'])))
  die("<pre>$contents");

if (($array = json_decode($contents, true)) === null)
  outputJsonError($contents);
else {
  $gjiUrl = "http://geojson.io/#data=data:application/json,".rawurlencode(json_encode($array));
  echo 
    (in_array($contentType, CONTENT_TYPES['geojson']) ?
      "<a href='$gjiUrl' target='_blank'>Visualiser le GeoJSON sur http://geojson.io</a><br>\n" : ''),
    "<pre>",replaceUrl(Yaml::dump(['json'=> 'ok', 'body'=> $array], $ydepth, 2));
  
}