<?php
namespace Spiders\Commands;
 
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputArgument;
use Spiders\Helpers\Webdriver;
 
class Test extends Command
{
    protected function configure()
    {
        $this->setName('test')
            ->setDescription('Test Command!')
            ->setHelp('Test component.');
    }
 
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $ssDir = __DIR__.'/../../../shareasale-files/60904/';

        $file = $ssDir.'60904.txt';
        $searchfor = '67663';
        
        // the following line prevents the browser from parsing this as HTML.
        header('Content-Type: text/plain');
        
        // get the file contents, assuming the file to be readable (and exist)
        $contents = file_get_contents($file);
        // escape special characters in the query
        $pattern = preg_quote($searchfor, '/');
        // finalise the regular expression, matching the whole line
        $pattern = "/^.*$pattern.*\$/m";
        // search, and store all matching occurences in $matches
        if(preg_match_all($pattern, $contents, $matches)){
           echo "Found matches:\n";
           print_r($matches[0]);
           //echo $matches[0];
        }
        else{
           echo "No matches found";
        }


        $fp = fopen($file, "r");
        $lin = 0;
        while(!feof($fp)) {
            $lin++;
            $linea = fgets($fp);
            echo $linea.PHP_EOL;
            if($lin==10) die('10 lineas');
        }
        fclose($fp);

        /*
        $fila = 1;
        if (($gestor = fopen($file, "r")) !== FALSE) {
            while (($datos = fgetcsv($gestor, 1000, "|")) !== FALSE) {
                $numero = count($datos);
                echo "<p> $numero de campos en la línea $fila: <br /></p>\n";
                $fila++;
              /*  for ($c=0; $c < $numero; $c++) {
                    echo $datos[$c] . "<br />\n";
                }*/
              /*  if($fila == 10) { die('listo'); }
            }
            fclose($gestor);
} */       


        /*
        $html = Webdriver::getHtml('https://www.industrywest.com/sale.html',false);
        
        //$elemento = $html->find('.product-item-name',0)->outertext;
      //  $output->writeln('Resultado: '.$elemento);
        $items = $html->find('.product-item-name a');
        foreach($items as $item) {
            echo $item->href.PHP_EOL;
            $html2 = Webdriver::getHtml($item->href);
            echo $html2->find('.page-title',0)->plaintext;
            echo ' | '.$html2->find('.special-price .price',0)->plaintext.PHP_EOL;
            unset($html2);
        }
        */
    }
}