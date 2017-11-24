<?php

/* ====   skipass   ==== */

/* ====   stagione in corso   ==== */
$stagione = '2017/18';

require_once(dirname(__FILE__).'/db_config.php');
require_once('/var/www/vendor/autoload.php');

use Spipu\Html2Pdf\Html2Pdf;

$transport = (new Swift_SmtpTransport('10.0.0.3', 25));
$mailer = new Swift_Mailer($transport);

// controllo se la funzione e' richiesta dalla pagina di gestione
if (isset($_GET["gestione"]))  {
    $gestione = (int)$_GET["gestione"];
    include ('/var/www/skipass/gestione/include/config.php');
} else {
    $gestione = 0;
    include ('/var/www/skipass/include/config.php');
}

// carico i files di dadabik per autenticazione

if ($db_library === 'adodb'){
	include('./include/adodb'.DADABIK_ADODB_VERSION.'/adodb.inc.php');
	
	// hack for oracle, all field names fetched in lower case
	if ($dbms_type === 'oci8po') {
		define('ADODB_ASSOC_CASE', 0); 
	} // end if
}

require ("./include/htmlawed/htmLawed.php");
include ("./include/languages/".$language.".php");
include ("./include/functions.php");
include ("./include/common_start.php");
include ("./include/check_installation.php");
include ("./include/check_login.php");
include ("./include/check_table.php");
// fine files di dadabik 


$today = getdate();
$today['mday'] = date('d');
$today['mon'] = date('m');
$today['year'];

$oggi = $today['mday']."/".$today['mon']."/".$today['year'];

$visualizza_prezzo = (int)$_GET["visualizza_prezzo"];


if (isset($_GET["debug"]))  {
	$debug = 1;
}

/* DEBUG MODE 
1 test
0 standard
*/

// togliere il commento per forzare il debug
$debug = 1;

$email_debug = array(
  "tomaso@tarvisiano.org",
  "heimat@iol.it"
);

if (isset($_GET["invia_mail"]))  {
	$invia_mail = (int)$_GET["invia_mail"];
} else {
	$invia_mail = 0;
}

// DATE_FORMAT(datainizio,'%d-%m-%Y %H:%i:%s') as inizio

/*        lettura database        */

switch ($tabella) {

    case 'voucher':
    $id_voucher = (int)$_GET["id_item"];

    $query = "
    SELECT 
    id_voucher, n_voucher, stagione, opzione_voucher, operatore,
    titolo, nome_cliente, indirizzo_cliente, email_cliente, telefono_cliente,
    data_arrivo, data_partenza, data_inserimento, data_emissione,
    testo_mail, testo_mail_cliente, allegato_mail,
    destinatario, n_notti, fascia_prezzo,
    gruppi_adulti, gruppi_scuole, gruppi_baby,
    alberghi.nome as nome_hotel, alberghi.indirizzo as indirizzo_hotel, alberghi.cap as cap, alberghi.citta as citta, 
    alberghi.telefono as telefono_hotel, alberghi.fax as fax_hotel, alberghi.email as email_hotel, 
    alberghi.cond_canc as cancellazione, alberghi.cond_canc_en as cancellazione_en, username_user,
    note, fattura, saldo,
    inviato_fornitore
    FROM voucher, alberghi
    WHERE 
    id_voucher ='$id_voucher'
    AND operatore = username_user
    ";

    $result = $db->query($query);

break;

}

$myrow = $result->fetch_array(MYSQLI_ASSOC);

ob_start();

if (isset($myrow["data_inserimento"])) {
$time = strtotime($myrow["data_inserimento"]);
$y = date('Y', $time);
}

if (empty($myrow["data_emissione"])) {
$myrow["data_emissione"] = $myrow["data_inserimento"];
}

switch ($tabella) {

    case 'voucher':

if ($myrow["n_voucher"])  {
    $numero_voucher = $myrow["n_voucher"];
} else {
    $numero_voucher = $id_voucher;
//  $result1 = $db->query("SELECT MAX(n_voucher) AS numero FROM voucher");
	$result1 = $db->query("SELECT count(*) AS numero FROM `voucher` WHERE stagione='$stagione'");
    $myrow1 = $result1->fetch_array(MYSQLI_ASSOC);
    $numero_voucher = $myrow1["numero"] + 1;
    $result2 = $db->query("UPDATE voucher set n_voucher='$numero_voucher', stagione='$stagione' where id_voucher ='$id_voucher'");
}

    $email_mittente = $myrow["email_hotel"];
    
switch ($email_mittente) {
    
    case 'simona@tarvisiano.org':
        $mittente = 'Simona Tuti';
        break;
    case 'cristiana@tarvisiano.org':
        $mittente = 'Cristiana Teot';
        break;
    default:
        $mittente = 'Co.Pro.Tur.';
        $email_mittente = 'consorzio@tarvisiano.org';
        }

include(dirname('__FILE__').'/templates/prezzi_skipass.php');
include(dirname('__FILE__').'/templates/testi_voucher.php');
include(dirname('__FILE__').'/templates/voucher_new.php');

	$nome_file = "voucher".$numero_voucher.".pdf";
	
	break;
}

$content = ob_get_clean();

$html2pdf = new HTML2PDF('P', 'A4', 'it');
$html2pdf->pdf->SetDisplayMode('fullpage');
$html2pdf->WriteHTML($content, isset($_GET['vuehtml']));

$testo_mail = '';

if ( $invia_mail == 0 )  {
	$html2pdf->Output($nome_file);
}

if ( $invia_mail > 0 )  {
//	$content_PDF = $html2pdf->Output('', 'S');
$content_PDF = $html2pdf->Output($nome_file, 'S');
}

// attesa 3 secondi per rimediare il problema dei pdf danneggiati
sleep(3);

/* lettura variabili per mail */
switch ($tabella) {

    case 'voucher':        

//    $email_mittente = 'consorzio@tarvisiano.org';

    $myrow["email_promotur"] = array(
                            "barbara.zelloth@promoturismo.fvg.it",
                            "consorzio@tarvisiano.org"
                            );
        if ( $invia_mail == '1' ) {
    	$subject = "Voucher skipass n.".$numero_voucher.' - '.$myrow["nome_cliente"];
    	$destinatario = $myrow["email_promotur"];
    	
    	if ( $debug == '1' ) {
    		$destinatario =  $email_debug;
    	}
    	
    	$testo_mail = <<<'EOD'
Spett .le Promoturismo FVG,

su presentazione, presso una delle casse Promotur, del seguente voucher vi preghiamo fornire al cliente i servizi richiesti.

Cordiali saluti    	

EOD;
    	
    	if ( !empty($myrow["testo_mail"]) ) {
    		$testo_mail = "Gentili signori,\n\n";
    		$testo_mail .= $myrow["testo_mail"]."\n\n";
    	}
    	
    } elseif  ( $invia_mail == '2' ) {
    	$subject = "Voucher skipass n.".$numero_voucher.' - '.$myrow["nome_hotel"];
    	$destinatario = array($myrow["email_cliente"]);
    	
    	if ( $debug == '1' ) {
    		$destinatario =  $email_debug;
    	}

       	$testo_mail = <<<'EOD'

Gentile cliente,

ringraziando per la sua prenotazione inoltriamo in allegato il voucher ski pass da stampare e pesentare

in cassa Promotur per il ritiro del suo ski pass.

Cordiali saluti
       	
EOD;
    	
    	if ( !empty($myrow["testo_mail_cliente"]) ) {
    		$testo_mail = "Gentili signori,\n\n";
    		$testo_mail .= addslashes($myrow["testo_mail_cliente"])."\n\n";
    	}
    	$testo_mail .= $mittente;
    }
    break;
    }


$testo_mail = nl2br(str_replace("\'","'",$testo_mail));

if ($invia_mail<>0) {
	// Create the message
	
	if ( $debug <> 1 ) {
	  if ( !empty($myrow["allegato_mail"]) ) {
		$message = Swift_Message::newInstance()
		->setSubject($subject)
		->setFrom(array($email_mittente => $mittente))
		->setReadReceiptTo($email_mittente)
//		->setBcc($email_mittente)
		->setTo($destinatario)
		->setBody($testo_mail, 'text/html')	
		->attach(Swift_Attachment::newInstance($content_PDF, $nome_file, 'application/pdf'))
		->attach(Swift_Attachment::fromPath('/var/www/skipass/uploads/' . $myrow["allegato_mail"] )); 
	      } else {
	    	$message = Swift_Message::newInstance()
		->setSubject($subject)
		->setFrom(array($email_mittente => $mittente))
		->setReadReceiptTo($email_mittente)
//		->setBcc($email_mittente)
		->setTo($destinatario)
		->setBody($testo_mail, 'text/html')	
		->attach(Swift_Attachment::newInstance($content_PDF, $nome_file, 'application/pdf'));
	  }

	} else {
		$message = Swift_Message::newInstance()
		->setSubject($subject)
		->setFrom(array($email_mittente => $mittente))
		->setTo($destinatario)
		// body
		->setBody($testo_mail, 'text/html')
		// And optionally an alternative body
		//->addPart('<q>Here is the message itself</q>', 'text/html')
		->attach(Swift_Attachment::newInstance($content_PDF, $nome_file, 'application/pdf'));
		}
	}

	if ($invia_mail<>0) {
		
/*		if ( $debug == '1' ) {
			echo $testo_mail;
		} else {
		*/
			$result = $mailer->send($message);
			if ($destinatario != "") {
				if ( $result ) { 
					echo "mail inviata<br /><br />";
					/* cambio il flag di posta inviata */

						$result3 = $db->query("UPDATE voucher set inviato_fornitore=inviato_fornitore+1 where id_voucher ='$id_voucher'");						
						$result4 = $db->query("INSERT INTO mail_inviate (id_voucher, destinatario, tabella, data_invio) VALUES ('$id_voucher', '$invia_mail', '$tabella', NOW())");
										
					$sPathPS = $_SERVER['SERVER_NAME'];
					echo '<a href="http://'.$sPathPS.'/index.php?table_name='.$tabella.'">Torna alla lista </a>';
					echo "<br /><br />";
					//echo $sPathPS;
				} else { 
					echo "errore"; 
					echo $mail->error_log; 
					//echo "Mail inviata<br>";
					//echo '<a href="http://voucher.tarvisiano.org">lista voucher</a>';
				} 
			} else { 
				echo "Errore: manca email destinatario<br /><br />";
				echo '<a href="http://'.$sPathPS.'/index.php?table_name='.$tabella.'">Torna alla lista </a>';
			}
//		}
	}



function getGUID(){
    if (function_exists('com_create_guid')){
        return com_create_guid();
    }else{
        mt_srand((double)microtime()*10000);//optional for php 4.2.0 and up.
        $charid = strtoupper(md5(uniqid(rand(), true)));
        $hyphen = chr(45);// "-"
//        $uuid = chr(123)// "{"
$uuid =      substr($charid, 0, 6).$hyphen
            .substr($charid, 6, 4);
            // .$hyphen
            // .substr($charid,12, 4).$hyphen
            // .substr($charid,16, 4).$hyphen
            // .substr($charid,20,12);
//            .chr(125);// "}"
        return $uuid;
    }
}

?>
