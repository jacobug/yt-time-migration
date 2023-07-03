<?php

use Lijinma\Commander;
use Lijinma\Color;
use GuzzleHttp\Exception\ClientException;

require __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/src/config.inc.php';

echo 'Youtrack Keep Time Logs Well v0.1' . PHP_EOL;

$cmd = new Commander();

$cmd
    ->version('0.1')
    ->option('-m, --me', 'Who am I')
    ->option('-t, --top', 'Top issues')
    ->option('-p, --prev', 'Previous month')
    ->parse($argv);

if (isset($cmd->me)) {

    $client = new \GuzzleHttp\Client();
    $response = $client->request('GET', 'https://issue.int.clickmeeting.com/api/users/me?fields=name,id', ['headers' => ['Authorization' => 'Bearer ' . API_KEY]]);

    echo Color::YELLOW . $response->getStatusCode() . Color::WHITE . PHP_EOL; // 200
    
    $content = json_decode($response->getBody());

    echo Color::GREEN . $content->name . Color::WHITE . PHP_EOL;

}

if (isset($cmd->top)) {
    $client = new \GuzzleHttp\Client();

    $logMonth = new \DateTime();

    if (isset($cmd->prev)) {
        $logMonth->modify('-1 month');
    }

    $startDate = $logMonth->format('Y-m-01');
    $endDate = $logMonth->format('Y-m-t');

    try {
        $response = $client->request('GET',
            YT_HOST . '/api/workItems?fields=issue(id,idReadable,summary),created,duration(presentation,minutes),author(name),creator(name),date,id,type&author='. $cmd->top .'&startDate='. $startDate . '&endDate=' . $endDate,
            ['headers' => ['Authorization' => 'Bearer ' . API_KEY]]);
    } catch (ClientException $e) {
        $response = $e->getResponse();
        $responseBody = $response->getBody()->getContents();
        echo Color::RED . json_decode($responseBody)->error . Color::WHITE . PHP_EOL;
        return;
    }

    echo Color::YELLOW . $response->getStatusCode() . Color::WHITE . PHP_EOL; // 200
    
    $workItems = json_decode($response->getBody());

    $smt = '';
    foreach($workItems as $workItem) {
        $logDate = new \DateTime(substr("@$workItem->date", 0, -3));
        if ($smt !== $logDate->format('d-m-Y')) {
            echo PHP_EOL . Color::WHITE . '---' . Color::WHITE . PHP_EOL;
            echo Color::WHITE .  $logDate->format('d-m-Y') . Color::WHITE . PHP_EOL;
            echo Color::WHITE . '---' . Color::WHITE . PHP_EOL;
        }
        
        echo Color::GREEN . $workItem->issue->idReadable . Color::WHITE . ' - ';
        echo Color::BLUE . $workItem->duration->presentation . Color::WHITE . PHP_EOL;
        echo Color::WHITE . $workItem->issue->summary . Color::WHITE . PHP_EOL;

        $line = readline("move to yours? [" . Color::YELLOW . 'n' . Color::WHITE . "] ");
        if ($line === 'y'){
            $params = [
                'usesMarkdown' => true,
                'date' => $workItem->date,
                'author' => [
                    'id' => ME_ID
                ],
                'duration' => [
                    'minutes' => $workItem->duration->minutes
                ],
                'type' => null,
            ];

            try {
                $response = $client->request('POST', YT_HOST . '/api/issues/'. $workItem->issue->id .'/timeTracking/workItems?fields=author(id,name),creator(id,name),date,duration(id,minutes,presentation),id,name,text,type(id,name)', ['headers' => ['Authorization' => 'Bearer ' . API_KEY], 'json' => $params]);
            } catch (ClientException $e) {
                $response = $e->getResponse();
                $responseBody = $response->getBody()->getContents();
                echo Color::RED . json_decode($responseBody)->error . Color::WHITE . PHP_EOL;
                return;
            }
            echo Color::YELLOW . $response->getStatusCode() . Color::WHITE . PHP_EOL; // 200
        }

        $smt = $logDate->format('d-m-Y');
    }

    echo Color::GREEN . '   ____                     .___         __        ___.     
  / ___\  ____    ____    __| _/        |__|  ____ \_ |__   
 / /_/  >/  _ \  /  _ \  / __ |         |  | /  _ \ | __ \  
 \___  /(  <_> )(  <_> )/ /_/ |         |  |(  <_> )| \_\ \ 
/_____/  \____/  \____/ \____ |     /\__|  | \____/ |___  / 
                             \/     \______|            \/  ' . Color::WHITE . PHP_EOL;
    
}