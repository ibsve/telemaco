<?php

namespace Infocamere\Telemaco;

use Goutte\Client;
use Symfony\Component\DomCrawler\Crawler as DomCrawler;
use Carbon\Carbon;
use Support\Str;

class TelemacoClient
{
    private $client;
    private $crwaler;
    
    public function __construct()
    {
        $this->client = new Client();
        $this->crawler = null;      
    }  

    /**
     * Effettua il login a Cert'ò
     * 
     * @param string $username
     * @param string $password
     * 
     * @return string
     */
    public function login($username, $password)
    {
        $message = json_decode('{"data":null,"codError":null,"message":null,"result":null}');

        $this->crawler = $this->client->request('GET', 'https://praticacdor.infocamere.it/ptco/common/Login.action');
        $form = $this->crawler->filter('.eacoForm')->form();
        $this->crawler = $this->client->submit($form, ['userid' => $username, 'password' => $password]);

        $text = $this->crawler->filter('body')->text();

        if (Str::contains($text, 'aperta')) {
            $form = $this->crawler->filter('.eacoForm')->form();
            $this->crawler = $this->client->submit($form, ['userid' => $username, 'password' => $password]);   
            $text = $this->crawler->filter('body')->text();
        }

        if (Str::contains($text, 'completa')) {
            $message->message = $text;
            $message->codError = "AU03";
        }

        if (Str::contains($text, 'scaduta')) {
            $message->message = $text;
            $message->codError = "AU04";
        }
        
        if (Str::contains($text, 'scadenza')) {
            $this->crawler = $this->client->click($this->crawler->selectLink('OK')->link());
        }

        if (Str::contains($text, 'riuscita')) {
            $message->message = "Autenticazione Telemaco non riuscita, utente e/o password errati.";
            $message->codError = "AU01";
        }

        if (Str::contains($text, 'nuova password')) {
            $message->message = "Autenticazione Telemaco non riuscita, password scaduta. Rinnovare la password accedendo al sito www.registroimprese.it.";
            $message->codError = "AU09";
        }
        
        $message->result = "OK";

        return $message;
    }

    /**
     * Chiude la sessione Telemaco/Cert'ò
     */
    public function logout()
    {
        $this->client->click($this->crawler->selectLink('Esci')->link());
    }

    /**
     * Ricava il fondo (voce diritti) del borsellino Telemaco
     * 
     * @return string
     */
    public function fondo()
    {      

        $diritti = $this->crawler->filter("td[width='125px']")->last()->text();

        return Str::substr($diritti, 2);
    }

    /**
     * Scarica da Cert'ò la distinta camerale
     * 
     * @param string $codPratica
     * 
     * @return string
     */
    public function distinta($codPratica)
    {
        $this->crawler = $this->client->request('POST', 'https://praticacdor.infocamere.it/ptco/common/ListaPraticheChiuse.action', [
            "opzioneFiltro" => "CODICE_PRATICA", 
            "valoreFiltro" => $codPratica, 
            "tipoPratica" => ""
        ]);

        $f = $this->crawler->selectLink($codPratica)->attr("href");

        $v = str_replace("'", '', substr($f, strpos($f, "(")+1, -1));

        $vv = explode(",", $v);

        $this->client->request("GET", "https://praticacdor.infocamere.it/ptco/common/DettaglioPraticaChiusa.action?codPraticaSel=".$vv[0]."&pridPraticaSel=".$vv[1]."&pvPraticaSel=".$vv[2]);

        $res = $this->client->getResponse();

        $html = $res->getContent();

        $t = Str::before(trim($html), 'Distinta');
        $uri = trim(substr($t, strrpos($t, "=")+1, -2));
        
        $this->client->request("GET", "https://praticacdor.infocamere.it/ptco/FpDownloadFile?id=$uri", [
            "cookies" => $this->client->getCookieJar()->all()
        ]);

        $pdf = $this->client->getResponse();

        return $pdf;
    }

    /**
     * Scarica da Cert'ò i documenti per la stampa in azienda (CO, copie, fatture)
     * 
     * @param string $codPratica
     * 
     * @return array
     */
    public function coe($codPratica)
    {
        $this->crawler = $this->client->request("POST", "https://praticacdor.infocamere.it/ptco/common/ListaPraticheChiuse.action", [
            "opzioneFiltro" => "CODICE_PRATICA", 
            "valoreFiltro" => $codPratica, 
            "tipoPratica" => ""
        ]);

        $f = $this->crawler->selectLink($codPratica)->attr("href");

        $v = str_replace("'", "", substr($f, strpos($f, "(")+1, -1));

        $vv = explode(",", $v);

        $this->client->request("GET", "https://praticacdor.infocamere.it/ptco/common/DettaglioPraticaChiusa.action?codPraticaSel=".$vv[0]."&pridPraticaSel=".$vv[1]."&pvPraticaSel=".$vv[2]);

        $res = $this->client->getResponse();

        $html = $res->getContent();

        $html = preg_replace("#<script(.*?)>(.*?)</script>#is", "", $html);

        $c = new DomCrawler();
        $c->addHtmlContent($html);

        $nco = $c->filter("#divNoteSportello")->nextAll()->first()->text();
        $nco = Str::after($this->before($nco, "-"), "to:");
        $nco = trim($nco);

        $f = $c->filter("#all tbody > tr")->each(function (DomCrawler $node, $i) use ($codPratica, $nco) {
            $pdf = $node->children()->first()->text();
            $pdf = empty($nco) ? $this->after($pdf, '_') : $this->after($this->replaceFirst($codPratica, $nco, $pdf), '_');

            $s = $node->filter("img")->first()->extract(["onclick"]);
            
            $ll = explode(",", str_replace('"', '', $this->after($this->before($s[0], ")"), "doStampaOnline(")));

            $this->client->request("POST", "/ptco/common/StampaModelloOnline.action", [
                "cookies" => $this->client->getCookieJar()->all(),
                "richiestaId" => $ll[0],
                "documentoId" => trim($ll[1]),
                "tipoDocumento" => trim($ll[2]),
                "user" => trim($ll[3]),
                "ente" => trim($ll[4]),
            ]);
                
            $res = $this->client->getResponse();
    
            $b64 = trim(Str::after(Str::before($res, "//var pdfDataB"), "pdf_data ="));
            
            $bdata = substr($b64, 1, strlen($b64)-3);

            return [
                'id' => $ll[0], 
                'file' => $pdf, 
                'data' => $bdata
            ];      
        });

        return $f;
    }

    /**
     * Aggiorna la password Telemaco
     * 
     * @param string $username
     * @param string $password
     * @param string $newPassword
     * 
     * @return mixed
     */
    public function aggiornaPassword($username, $password, $newPassword = null)
    {
        if (is_null($newPassword)) {
            $newPassword = $this->random(12);
        }

        $this->crawler = $this->client->request('GET', 'https://login.infocamere.it/eacologin/changePwd.action');
        $form = $this->crawler->selectButton('Conferma')->form();

        $this->crawler = $this->client->submit($form, [
            'userid' => $username, 
            'password' => $password, 
            'new_password' => $newPassword, 
            'cfr_password' => $newPassword
        ]);

        $text = $this->crawler->text();
        
        if (Str::contains($text, 'sostituita')) {            
            return [
                'username' => $username, 
                'password' => $newPassword, 
                'data_scadenza' => Carbon::now('Europe/Rome')->addMonths(6)->toDateString(),
                'message' => null,
            ];
        }
        else {
            return [
                'message' => $text,
            ];
        }
    }

    /**
     * Genera il file XML con i dati per il tipo specificato (co, va, dtr)
     * 
     * @param string $tipo
     * @param array $dati
     * 
     * @return array
     */
    public function xml(array $dati, $tipo = 'co')
    {
        $f = 'xml'.$tipo;
        
        return $this->$f($dati);
    }

    private function xmlco($dati)
    {
        $fp = "#####FINEPAGINA#####";
        
        $doc = new \DOMDocument();
        $doc->encoding = "UTF-8";
        $doc->formatOutput = true;
        $doc->loadXML(file_get_contents(dirname(__FILE__)."/template/Mbase_PTCO_CO.xml"));
        $provincia = $doc->getElementsByTagName('Provincia')->item(0);
        $provincia->setAttribute('siglaProv', $dati['prov']);
        $numero = $doc->getElementsByTagName('Numero')->item(0);
        $numero->nodeValue = $dati['rea'];
        $codfisc = $doc->getElementsByTagName('CodiceFiscale')->item(0);
        $codfisc->nodeValue = $dati['cf'];
        $denom = $doc->getElementsByTagName('Denominazione')->item(0);
        $denom->nodeValue = htmlentities($dati['denominazione'], ENT_XML1);
        $sped = $doc->getElementsByTagName('Speditore')->item(0);
        $sped->nodeValue = htmlentities($dati['speditore'], ENT_XML1);
        $dest = $doc->getElementsByTagName('Destinatario')->item(0);
        $dest->nodeValue = htmlentities($dati['destinatario'], ENT_XML1);                
        $pdest = $doc->getElementsByTagName('PaeseDestinazione')->item(0);
        $pdest->setAttribute('nomePaeseDestinazione', $dati['paese_destinazione']);
        $porig = explode(',', $dati['paese_origine']);
        $paesi_origine = $doc->getElementsByTagName('PaesiOrigine')->item(0);
        foreach ($porig as $po) {
            if (!empty(trim($po))) {
                $node = $doc->createElement("Paese");
                $node->nodeValue = trim($po);
                $paesi_origine->appendChild($node);
            }
        }
        $fatturato = $doc->getElementsByTagName('FatturatoTotale')->item(0);
        if (!empty($dati['valore_merce'])) {
            $fatturato->nodeValue = $dati['valore_merce'];
        }
        $trasp = $doc->getElementsByTagName('Trasporto')->item(0);
        $trasp->nodeValue = htmlentities($dati['trasporto'], ENT_XML1);
        $osservazioni = $doc->getElementsByTagName('Osservazioni')->item(0);
        $osservazioni->nodeValue = htmlentities($dati['osservazioni'], ENT_XML1);

        $dati_cert = $doc->getElementsByTagName('DATI-CERTIFICATO')->item(0);

        if (Str::contains($dati['merci'], $fp)) {
            $t6 = explode($fp, $dati['merci']);
            $t7 = explode($fp, $dati['quantita']);

            for ($i=0; $i < count($t6); $i++) {
                $noded = $doc->createElement("DettaglioMerci");
                $noded->nodeValue = htmlentities($t6[$i], ENT_XML1);
                $dati_cert->appendChild($noded);
            }

            for ($i=0; $i < count($t7); $i++) {
                $nodeq = $doc->createElement("Quantita");
                $nodeq->nodeValue = htmlentities($t7[$i], ENT_XML1);
                $dati_cert->appendChild($nodeq);
            }
        }
        else {
            $merci = $doc->createElement("DettaglioMerci");
            $merci->nodeValue = htmlentities($dati['merci'], ENT_XML1);
            $dati_cert->appendChild($merci);
            $quant = $doc->createElement("Quantita");
            $quant->nodeValue = htmlentities($dati['quantita'], ENT_XML1);
            $dati_cert->appendChild($quant);
        }

        $orme = 'ORCOM';

        if (strlen(trim($dati['modifiche_subite']))+strlen(trim($dati['azienda_modifiche'])) > 0) {
            $orme = 'MDCOM';
        }
        
        if (strlen(trim($dati['paese_extraue']))+strlen(trim($dati['documentazione_allegata'])) > 0)  {
            $orme = 'OREST';
        }        

        $ormerce = $doc->getElementsByTagName('ORIGINEMERCE')->item(0);
        $ormerce->nodeValue = $orme;

        $paese_ue = $doc->getElementsByTagName('PaeseComunitario')->item(0);
        $paese_ue->nodeValue = $dati['paese_comunitario'];
        $modprod = $doc->getElementsByTagName('ModalitaProduzione')->item(0);
        $modprod->nodeValue = $dati['modalita_produzione'];
        $azprod = $doc->getElementsByTagName('AziendaProduttrice')->item(0);
        $azprod->nodeValue = htmlentities($dati['azienda_produttrice'], ENT_XML1);
        $mod = $doc->getElementsByTagName('ModificheSubite')->item(0);
        $mod->nodeValue = $dati['modifiche_subite'];
        $azmod = $doc->getElementsByTagName('AziendaModifiche')->item(0);
        $azmod->nodeValue = htmlentities($dati['azienda_modifiche'], ENT_XML1);
        $paese_extraue = $doc->getElementsByTagName('PaeseExtraComunitario')->item(0);
        $paese_extraue->nodeValue = $dati['paese_extraue'];
        $docall = $doc->getElementsByTagName('DocumentazioneAllegata')->item(0);
        $docall->nodeValue = htmlentities($dati['documentazione_allegata'], ENT_XML1);

        $fatt = $doc->getElementsByTagName('RIEPILOGO-FATTURE')->item(0);

        foreach ($dati['fatture'] as $fattura) {
            $ff = $doc->createElement('Fattura');
            $h = $fatt->appendChild($ff);
            $h->setAttribute('numero', $fattura['numero']);
            $h->setAttribute('data', $fattura['data'].'+01:00');
        }

        return $doc->saveXML();
    }

    private function xmlva($dati)
    {
        $doc = new \DOMDocument();
        $doc->encoding = "UTF-8";
        $doc->formatOutput = true;
        $doc->loadXML(file_get_contents(dirname(__FILE__)."/template/Mbase_PTCO_VA.xml"));
        $provincia = $doc->getElementsByTagName('Provincia')->item(0);
        $provincia->setAttribute('siglaProv', $dati['prov']);
        $numero = $doc->getElementsByTagName('Numero')->item(0);
        $numero->nodeValue = $dati['rea'];
        $codfisc = $doc->getElementsByTagName('CodiceFiscale')->item(0);
        $codfisc->nodeValue = $dati['cf'];
        $denom = $doc->getElementsByTagName('Denominazione')->item(0);
        $denom->nodeValue = htmlentities($dati['denominazione'], ENT_XML1);
        $descr = $doc->getElementsByTagName('SoggettoRichiedente')->item(0);
        $descr->nodeValue = htmlentities($dati['descrizione'], ENT_XML1);
        $note = $doc->getElementsByTagName('NoteRichiesta')->item(0);
        $note->nodeValue = htmlentities($dati['note'], ENT_XML1);

        return $doc->saveXML();
    }

    private function xmldd($dati)
    {
        $doc = new \DOMDocument();
        $doc->encoding = "UTF-8";
        $doc->formatOutput = true;
        $doc->loadXML(file_get_contents(dirname(__FILE__)."/template/Mbase_PTCO_DD.xml"));
        $provincia = $doc->getElementsByTagName('Provincia')->item(0);
        $provincia->setAttribute('siglaProv', $dati['prov']);
        $numero = $doc->getElementsByTagName('Numero')->item(0);
        $numero->nodeValue = $dati['rea'];
        $codfisc = $doc->getElementsByTagName('CodiceFiscale')->item(0);
        $codfisc->nodeValue = $dati['cf'];
        $denom = $doc->getElementsByTagName('Denominazione')->item(0);
        $denom->nodeValue = htmlentities($dati['denominazione'], ENT_XML1);
        $descr = $doc->getElementsByTagName('DataDenuncia')->item(0);
        $descr->nodeValue = htmlentities(date('Y-m-d').'+01:00', ENT_XML1);
        
        $docden = $doc->getElementsByTagName('DocumentiDenunciati')->item(0);

        foreach ($dati['co'] as $co) {
            $dd = $doc->createElement('DocumentoDenunciato');
            $h = $docden->appendChild($dd);
            $h->setAttribute('identificativo', $co);
        }

        return $doc->saveXML();
    }

    private function xmldf($dati)
    {
        $doc = new \DOMDocument();
        $doc->encoding = "UTF-8";
        $doc->formatOutput = true;
        $doc->loadXML(file_get_contents(dirname(__FILE__)."/template/Mbase_PTCO_DF.xml"));
        $provincia = $doc->getElementsByTagName('Provincia')->item(0);
        $provincia->setAttribute('siglaProv', $dati['prov']);
        $numero = $doc->getElementsByTagName('Numero')->item(0);
        $numero->nodeValue = $dati['rea'];
        $codfisc = $doc->getElementsByTagName('CodiceFiscale')->item(0);
        $codfisc->nodeValue = $dati['cf'];
        $denom = $doc->getElementsByTagName('Denominazione')->item(0);
        $denom->nodeValue = htmlentities($dati['denominazione'], ENT_XML1);
        $descr = $doc->getElementsByTagName('DataDenuncia')->item(0);
        $descr->nodeValue = htmlentities(date('Y-m-d').'+01:00', ENT_XML1);
        
        $docden = $doc->getElementsByTagName('DocumentiDenunciati')->item(0);

        foreach ($dati['co'] as $co) {
            $dd = $doc->createElement('DocumentoDenunciato');
            $h = $docden->appendChild($dd);
            $h->setAttribute('identificativo', $co);
        }

        return $doc->saveXML();
    }

    private function xmldtr($dati)
    {
        $doc = new \DOMDocument();
        $doc->encoding = "UTF-8";
        $doc->formatOutput = true;
        $doc->loadXML(file_get_contents(dirname(__FILE__)."/template/Mbase_PTCO_DTR.xml"));
        $cciaa = $doc->getElementsByTagName('CciaaRiferimento')->item(0);
        $cciaa->nodeValue = $dati['cciaa'];
        $tipo = $doc->getElementsByTagName('TipoPratica')->item(0);
        $tipo->nodeValue = $dati['tipo'];
    }
}