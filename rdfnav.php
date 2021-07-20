<?php
/*PhpDoc:
name: rdfnav.php
title: rdfnav.php - navigateur RDF
doc: |
  Le format RDF/XML est peu lisible. Turtle l'est beaucoup plus.
  Turtle a cependant l'inconvénient
    - d'une part de ne pas permettre de naviguer au sein des ressources cliquer sur les URI des ressources,
    - d'autre part de ne pas s'afficher simplement dans le nigateur mais de provoquer un téléchargement.
  Enfin, JSON-LD est plus lisible que RDF/XML mais moins que Turtle ; il est toutefois bien adapté pour générer du RDF.

  L'objectif du script est de faciliter la navigation au sein de données liées exposées en RDF en:
    - convertissant ces données en Turtle à partir de JSON-LD ou de RDF/XML,
    - affichant ce Turtle en HTML avec de liens cliquables vers les ressources liées.
  
  Le script prend en paramètre un URL/URI de ressource.
  Il effectue un GET sur cet URL/URI en positionnant l'en-tête Accept à
    application/x-turtle, application/ld+json, application/rdf+xml
  
  Si le Content-Type est application/x-turtle ou text/plain ou text/turtle
  alors pas de conversion
  sinonSi le Content-Type est application/ld+json ou application/rdf+xml
  alors conversion en Turtle
  sinon
    erreur
  Affichage du Turtle formatté en Html
   - avec une barre fournissant l'URI/URL,
   - et permettant de clicker sur les liens en renvoyant vers le navigateur.
   
  Le seul paramètre du script est l'URL/URI en paramètre GET url.
journal: |
  20/7/2021:
    - création
*/
require __DIR__.'/vendor/autoload.php';

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

echo "<!DOCTYPE HTML><html><head><meta charset='UTF-8'><title>rdfnav</title></head><body>\n";

//echo '<pre>'; print_r($_SERVER);
$url = $_GET['url'] ?? '';

echo "<table><tr>" // le formulaire d'affichage et de saisie de l'URL
      . "<td>url:</td>"
      . "<td><table border=1><form><tr>"
      . "<td><input type='text' size=130 name='url' value='$url'/></td>\n"
      . "<td><input type='submit' value='Go'></td>\n"
      . "</tr></form></table></td>\n"
      . "</tr></table>\n";

if (!$url)
  die("Url vide");

$opts = [
  'http'=> [
    'method'=> 'GET',
    'ignore_errors' => '1',
    'header'=> "Accept: application/x-turtle,application/ld+json,application/rdf+xml\r\n"
              ."Accept-language: en\r\n"
              ."Cookie: foo=bar\r\n",
  ],
];
//echo "url=$url\n";
if (FALSE === $contents = @file_get_contents($url, false, stream_context_create($opts))) {
  echo "<pre><b>ERREUR</b>, http_response_header = "; print_r(response_header($http_response_header)); echo "</pre>\n";
  die();
}

$response_header = response_header($http_response_header);
if (!preg_match('!^HTTP/1\.. 200!', $response_header['returnCode'])) {
  echo "<pre><b>ERREUR</b>, http_response_header = "; print_r($response_header); echo "</pre>\n";
  die();
}

//echo "<pre><b>OK</b>, http_response_header = "; print_r($response_header); echo "</pre>\n";

if (preg_match('!^application/ld\+json!', $response_header['Content-Type'])) {
  $data = new \EasyRdf\Graph($url);
  $data->parse($contents, 'jsonld', $url);
  $turtle = $data->serialise('turtle');
}
elseif (preg_match('!^(application/rdf\+xml|text/xml)!', $response_header['Content-Type'])) {
  $data = new \EasyRdf\Graph($url);
  $data->parse($contents, 'rdf', $url);
  $turtle = $data->serialise('turtle');
}
elseif (preg_match('!^(application/x-turtle|text/plain|text/turtle)!', $response_header['Content-Type'])) {
  $turtle = $contents;
}
else {
  die("En-tête ".$response_header['Content-Type']." non comprise\n");
}

echo '<pre>';

// le texte à afficher est dans $turtle
while (preg_match('!<(http[^>]+)>!', $turtle, $matches)) {
  //print_r($matches);
  $url = $matches[1];
  $replacement = "<a href='?url=".urlencode($url)."'>$url</a>";
  $turtle = preg_replace('!<http[^>]+>!', "&lt;$replacement&gt;", $turtle, 1);
  //die();
  //break;
}

echo $turtle;
