<?php
$urls = [
  'https://www.sfea.org.tw/SfeaMap_API/api/getDrawSearch?qs=120.11730194091798,23.059516273509317,120.11730194091798,23.29874789647129,120.25806427001955,23.29874789647129,120.25806427001955,23.059516273509317,120.11730194091798,23.059516273509317&area=%E5%85%88%E8%A1%8C%E5%8D%80&Layer[]=A&Layer[]=I&Layer[]=G&Layer[]=C&Layer[]=H&Layer[]=B&Layer[]=J&Layer[]=L&Layer[]=E&Layer[]=F&Layer[]=K&Layer[]=D',
  'https://www.sfea.org.tw/SfeaMap_API/api/getDrawSearch?qs=120.11730194091798,23.059516273509317,120.11730194091798,23.29874789647129,120.25806427001955,23.29874789647129,120.25806427001955,23.059516273509317,120.11730194091798,23.059516273509317&area=%E5%84%AA%E5%85%88%E5%8D%80&Layer[]=A&Layer[]=I&Layer[]=G&Layer[]=C&Layer[]=H&Layer[]=B&Layer[]=J&Layer[]=L&Layer[]=E&Layer[]=F&Layer[]=K&Layer[]=D',
  'https://www.sfea.org.tw/SfeaMap_API/api/getDrawSearch?qs=120.11730194091798,23.059516273509317,120.11730194091798,23.29874789647129,120.25806427001955,23.29874789647129,120.25806427001955,23.059516273509317,120.11730194091798,23.059516273509317&area=%E9%97%9C%E6%B3%A8%E6%B8%9B%E7%B7%A9%E5%8D%80&Layer[]=A&Layer[]=I&Layer[]=G&Layer[]=C&Layer[]=H&Layer[]=B&Layer[]=J&Layer[]=L&Layer[]=E&Layer[]=F&Layer[]=K&Layer[]=D'
];
$taiwanPolygon = '122.17251881895851,25.391702529446846,119.86042407439356,25.391702529446846,119.86042407439356,21.83322404580956,122.17251881895851,21.83322404580956,122.17251881895851,25.391702529446846';
foreach($urls AS $url) {
  $parts = parse_url($url);
  parse_str($parts['query'], $params);
  $params['qs'] = $taiwanPolygon;

  // build new url
  $newUrl = $parts['scheme'] . '://' . $parts['host'] . $parts['path'] . '?' . http_build_query($params);
  $targetFile = dirname(__DIR__) . '/raw/json/' . $params['area'] . '.json';
  file_put_contents($targetFile, file_get_contents($newUrl));
}

