<?php

namespace App\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\Question;
use Symfony\Contracts\HttpClient\HttpClientInterface;

#[AsCommand(name: "app:weather", description: "Lists the weather of the given latitude and longitude")]
class WeatherCommand extends Command
{

    public function __construct(private readonly HttpClientInterface $httpClient, string $name = null,)
    {
        parent::__construct($name);
    }
    protected function configure(): void
    {
        $this->addArgument('lat', InputArgument::REQUIRED)
            ->addArgument('lng', InputArgument::REQUIRED)
            ->addOption(
                'days',
                'd',
                InputOption::VALUE_OPTIONAL,
                7
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output) : int
    {
        // 1. getting inputs ( arguments , options ... )
        $lat = (float)$input->getArgument('lat');
        $lng = (float)$input->getArgument('lng');
        $days = (int)$input->getOption('days');

        // 2. asking question about temperature measurement unit
        $helper = $this->getHelper('question');
        $question = new Question("Do you prefer temperature in fahrenheit or celsius ? \n");
        $question->setAutocompleterValues(['fahrenheit', 'celsius']);
        $temperatureUnit = $helper->ask($input, $output, $question);

        // 3. showing a progress bar
        $progressBar = new ProgressBar($output);
        $progressBar->start();
        sleep(1);
        $progressBar->setProgress(50);
        // 4. fetching data from forecast api
        $response = $this->httpClient->request('GET',
            'https://api.open-meteo.com/v1/forecast',
            ['query' => [
                'latitude' => $lat,
                'longitude' => $lng,
                'daily' => 'temperature_2m_max,temperature_2m_min',
                'timezone' => 'Europe/Paris',
                'forecast_days' => $days,
                'temperature_unit' => $temperatureUnit,
            ]]
        )->toArray();
        // 5. stopping progress bar
        $progressBar->setProgress(100);
        $progressBar->finish();
        $output->writeln('');

        // 6. displaying data as table
        $table = new Table($output);
        $table->setHeaders(['Day', 'Temperature Min', 'Temperature Max']);
        $rows = [];
        foreach ($response['daily']['time'] as $key => $date) {
            $rows[] = [
                $date,
                $response['daily']['temperature_2m_min'][$key],
                $response['daily']['temperature_2m_max'][$key]
            ];

        }
        $table->setRows($rows);
        $table->render();


        return Command::SUCCESS;
    }

}