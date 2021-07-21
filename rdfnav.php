<?php
/*PhpDoc:
name: rdfnav.php
title: rdfnav.php - navigateur RDF
doc: |
  Voir la doc dans le code ou dans <a href='http://localhost/geoapi/tools/rdfnav.php'>l'exécution du script</a>.
journal: |
  21/7/2021:
    - gestion du renvoi vers un document non LD
  20/7/2021:
    - création
*/
require __DIR__.'/vendor/autoload.php';

$doc = <<<EOT
<h2>Documentation</h2>
Le format RDF/XML est peu lisible. Turtle l'est beaucoup plus.<br>
Turtle a cependant comme inconvénients :<ul>
  <li>de ne pas s'afficher simplement dans le navigateur mais de provoquer un téléchargement,
  <li>de ne pas permettre de naviguer au sein des ressources en cliquant sur leur URI.
</ul>
Enfin, JSON-LD est plus lisible que RDF/XML mais moins que Turtle ; il est toutefois bien adapté pour générer du RDF.
</p>

L'objectif de ce script est de faciliter la navigation au sein de données liées exposées en RDF en:<ul>
  <li>récupérant par un GET HTTP le contenu associé à un URI en demandant les formats Turtle, RDF/XML ou JSON-LD,
  <li>convertissant ce contenu en Turtle lorsqu'il est en JSON-LD ou en RDF/XML,
  <li>affichant ce Turtle en HTML avec de liens cliquables vers les ressources liées.
</ul>

Le script prend en paramètre GET url un URL/URI de ressource.<br>
Il effectue un GET sur cet URL/URI en positionnant l'en-tête Accept à<br>
  <code>application/x-turtle, application/ld+json, application/rdf+xml</code>
</p>

<h4>Pseudo-code</h4>
<code>
Si le Content-Type est application/x-turtle ou text/plain ou text/turtle<br>
alors pas de conversion<br>
sinonSi le Content-Type est application/ld+json ou application/rdf+xml<br>
alors conversion en Turtle<br>
sinonSi le Content-Type est text/html ou application/pdf ou application/json ou text/csv<br>
alors renvoi vers le document<br>
sinon<br>
&nbsp;&nbsp;erreur<br>

Affichage du Turtle formatté en Html<br>
 - avec une barre fournissant l'URI/URL et permettant d'en saisir un,<br>
 - et permettant de cliquer sur les liens en renvoyant vers le navigateur.<br>
</code>

<h4>Exemples</h4>
<ul>
<li><a href='?url=https://dido.geoapi.fr/v1/dcatexport.jsonld'>https://dido.geoapi.fr/v1/dcatexport.jsonld</a>
<li><a href='?url=http://localhost/geoapi/dido/api.php/v1/dcatexport.rdf'>http://localhost/geoapi/dido/api.php/v1/dcatexport.rdf (uniquement en local)</a>
</ul>
EOT;
 
// transforme le contenu de $http_response_header en un array clés => valeurs
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

$form = "<tr>" // le formulaire d'affichage et de saisie de l'URL sous la forme d'une ligne d'une table HTML
      . "<td>url:</td>"
      . "<td><form>"
      . "<input type='text' size=130 name='url' value='$url'/>\n"
      . "<input type='submit' value='Get'>"
      . "</form></td>\n"
      . "</tr>\n";
//echo "<table border=1>$form</table>\n";

if (!$url)
  die("<table border=1>$form<tr><td>statut</td><td><b>Url vide</b></td></tr></table>$doc");

$context = stream_context_create([
  'http'=> [
    'method'=> 'GET',
    'ignore_errors' => '1',
    'header'=> "Accept: application/x-turtle,application/ld+json,application/rdf+xml\r\n"
              ."Accept-language: en\r\n"
              ."Cookie: foo=bar\r\n",
  ],
]);
//echo "url=$url\n";
$contents = @file_get_contents($url, false, $context);
$response_header = response_header($http_response_header ?? []);
if (($contents === FALSE) || !preg_match('!^HTTP/1\.. 200!', $response_header['returnCode'])) {
  echo "<table border=1>$form<tr><td>statut</td><td><pre><b>ERREUR</b>: ",
       json_encode($response_header, JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE),
       "</pre></td></tr></table>\n";
  die();
}
//echo "<pre><b>OK</b>, http_response_header = "; print_r($response_header); echo "</pre>\n";

$cType = $response_header['Content-Type'];
if (preg_match('!^application/ld\+json!', $cType)) { // JSON-LD
  $data = new \EasyRdf\Graph($url);
  $data->parse($contents, 'jsonld', $url);
  $contents = $data->serialise('turtle');
}
elseif (preg_match('!^(application/rdf\+xml|text/xml)!', $cType)) { // RDF/XML
  $data = new \EasyRdf\Graph($url);
  $data->parse($contents, 'rdf', $url);
  $contents = $data->serialise('turtle');
}
elseif (preg_match('!^(application/x-turtle|text/plain|text/turtle)!', $cType)) { // Turtle => pas de conversion
}
elseif (preg_match('!^(text/html|application/json|text/csv|application/pdf)!', $cType)) { // doc classique => renvoie vers lui
  header("Location: $url");
  die("Renvoi vers $url\n");
}
else { // type non reconnu
  die("<table border=1>$form"
      ."<tr><td>statut</td><td><b>En-tête ".$response_header['Content-Type']." non comprise</b></td></tr>"
      ."</table>\n");
}

echo "<table border=1>$form",
     "<tr><td>Content-Type</td><td>",$response_header['Content-Type'],"</td></tr>",
     "</table>\n",
     '<pre>';

// le texte à afficher est en Turtle dans $contents
while (preg_match('!<((http|mailto)[^>]+)>!', $contents, $matches)) {
  //print_r($matches);
  $url = $matches[1];
  $replacement = "<a href='?url=".urlencode($url)."'>$url</a>";
  $contents = preg_replace('!<(http|mailto)[^>]+>!', "&lt;$replacement&gt;", $contents, 1);
}

echo $contents;
