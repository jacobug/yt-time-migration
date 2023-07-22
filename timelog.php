<?php

use Lijinma\Commander;
use Lijinma\Color;
use GuzzleHttp\Exception\ClientException;

require __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/src/config.inc.php';

echo PHP_EOL;
echo 'Youtrack Keep Time Logs Well v0.1' . PHP_EOL;

$cmd = new Commander();

$cmd
    ->version('0.1')
    ->option('-m, --me', 'Who am I')
    ->option('-t, --top [login]', 'Issues logged by certain user')
    ->option('-p, --prev', 'Last month modifier')
    ->option('-d, --dry', 'Just show all issues')
    ->option('-x, --holidays', 'Enter day offs')
    ->parse($argv);

if (isset($cmd->me)) {

    $client = new \GuzzleHttp\Client();
    $response = $client->request('GET', 'https://issue.int.clickmeeting.com/api/users/me?fields=name,id', ['headers' => ['Authorization' => 'Bearer ' . API_KEY]]);
    
    $content = json_decode($response->getBody());

    echo PHP_EOL;
    echo sprintf('Logged as: %s', Color::GREEN . $content->name . Color::WHITE) . PHP_EOL;
    echo sprintf('Your ID is: %s', Color::GREEN . $content->id . Color::WHITE) . PHP_EOL;
}

if (isset($cmd->top)) {
    $client = new \GuzzleHttp\Client();

    $logMonth = new \DateTime();
    
    if (isset($cmd->prev)) {
        $logMonth->modify('-1 month');
    }

    $startDate = $logMonth->format('Y-m-01');
    $endDate = $logMonth->format('Y-m-t');

    $holidaysResponse = $client->request('GET', sprintf('https://openholidaysapi.org/PublicHolidays?countryIsoCode=PL&languageIsoCode=PL&validFrom=%s&validTo=%s', $startDate, $endDate));
    $holidays = json_decode($holidaysResponse->getBody()->getContents());
    if (empty($holidays)) {
        echo 'No holidays this month' . PHP_EOL;
    }

    $dayoffs = [];
    if (isset($cmd->holidays)) {
        $line = 1;
        while (!empty($line)) {
            $line = readline(Color::LIGHT_GRAY . "want to add day off? enter day of the month when you were absent: ");
            if (!empty($line)) {
                $dayoffs[] = $logMonth->format(sprintf('Y-m-%s', (strlen($line) === 1) ? '0' . $line : $line));
            }
        }
    }

    $holidaysKV = [];
    foreach ($holidays as $holiday) {
        $holidaysKV[$holiday->startDate] = $holiday->name[0]->text;
    }

    try {
        $response = $client->request(
            'GET',
            YT_HOST . sprintf(
                '/api/workItems?fields=issue(id,idReadable,summary),created,duration(presentation,minutes),author(name),creator(name),date,id,type&author=%s&startDate=%s&endDate=%s', 
                $cmd->top,
                $startDate, 
                $endDate
            ),
            ['headers' => ['Authorization' => 'Bearer ' . API_KEY]]
        );
    } catch (ClientException $e) {
        $response = $e->getResponse();
        $responseBody = $response->getBody()->getContents();
        echo Color::RED . json_decode($responseBody)->error . Color::WHITE . PHP_EOL;
        return;
    }
    
    $workItems = json_decode($response->getBody());

    usort($workItems, fn($a, $b) => strcmp($a->date, $b->date));

    $smt = '';
    foreach($workItems as $workItem) {
        $logDate = new \DateTime(substr("@$workItem->date", 0, -3));
        if ($smt !== $logDate->format('d-m-Y')) {
            echo PHP_EOL . Color::WHITE . '---' . Color::WHITE . PHP_EOL;
            echo Color::WHITE .  $logDate->format('d-m-Y') . Color::WHITE;
            echo in_array($logDate->format('Y-m-d'), array_keys($holidaysKV)) ? ' ' . Color::BG_RED . $holidaysKV[$logDate->format('Y-m-d')] . Color::BG_BLACK : '';
            echo !empty(array_keys($dayoffs, $logDate->format('Y-m-d'))) ? ' ' . Color::BG_YELLOW . 'Day off' . Color::BG_BLACK : '';
            echo PHP_EOL;
            echo Color::WHITE . '---' . Color::WHITE . PHP_EOL;
        }
        
        echo Color::GREEN . $workItem->issue->idReadable . Color::WHITE . ' - ';
        echo Color::BLUE . $workItem->duration->presentation . Color::WHITE . PHP_EOL;
        echo Color::LIGHT_GRAY . $workItem->issue->summary . Color::WHITE . PHP_EOL . PHP_EOL;

        if (isset($cmd->dry)) {
            $line = null;
        } else {
            $line = readline("copy to yours? [" . Color::YELLOW . 'n' . Color::WHITE . "] ");
        }

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
                $response = $client->request(
                    'POST', 
                    YT_HOST . '/api/issues/'. $workItem->issue->id .'/timeTracking/workItems?fields=author(id,name),creator(id,name),date,duration(id,minutes,presentation),id,name,text,type(id,name)',
                    ['headers' => ['Authorization' => 'Bearer ' . API_KEY], 'json' => $params]
                    );
            } catch (ClientException $e) {
                $response = $e->getResponse();
                $responseBody = $response->getBody()->getContents();
                echo Color::RED . json_decode($responseBody)->error . Color::WHITE . PHP_EOL;
                return;
            }
        }

        $smt = $logDate->format('d-m-Y');
    }
    
    echo PHP_EOL;
    echo Color::GREEN . '   ____                     .___         __        ___.     
  / ___\  ____    ____    __| _/        |__|  ____ \_ |__   
 / /_/  >/  _ \  /  _ \  / __ |         |  | /  _ \ | __ \  
 \___  /(  <_> )(  <_> )/ /_/ |         |  |(  <_> )| \_\ \ 
/_____/  \____/  \____/ \____ |     /\__|  | \____/ |___  / 
                             \/     \______|            \/  ' . Color::WHITE . PHP_EOL;
    
}

echo PHP_EOL;
