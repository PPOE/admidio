<?php
/******************************************************************************
 * Update der Admidio-Datenbank auf eine neue Version
 *
 * Copyright    : (c) 2004 - 2009 The Admidio Team
 * Homepage     : http://www.admidio.org
 * Module-Owner : Markus Fassbender
 * License      : GNU Public License 2 http://www.gnu.org/licenses/gpl-2.0.html
 *
 * Uebergaben:
 *
 * mode     = 1 : (Default) Statuspruefung und Anzeige, ob ein Update notwendig ist
 *            2 : Update durchfuehren
 *            3 : Ergebnis des Updates ausgeben
 *
 *****************************************************************************/

require_once('install_functions.php');

// Uebergabevariablen pruefen

if(isset($_GET['mode']) && is_numeric($_GET['mode']))
{
    $req_mode = $_GET['mode'];
}
else
{
    $req_mode = 1;
}

// Konstanten und Konfigurationsdatei einbinden
require_once(substr(__FILE__, 0, strpos(__FILE__, 'adm_install')-1). '/config.php');
require_once(substr(__FILE__, 0, strpos(__FILE__, 'adm_install')-1). '/adm_program/system/constants.php');

 // Standard-Praefix ist adm auch wegen Kompatibilitaet zu alten Versionen
if(strlen($g_tbl_praefix) == 0)
{
    $g_tbl_praefix = 'adm';
}

// Default-DB-Type ist immer MySql
if(!isset($g_db_type))
{
    $g_db_type = 'mysql';
}

require_once(SERVER_PATH. '/adm_program/system/db/'. $g_db_type. '.php');
require_once(SERVER_PATH. '/adm_program/system/string.php');
require_once(SERVER_PATH. '/adm_program/system/function.php');
require_once(SERVER_PATH. '/adm_program/system/classes/organization.php');

 // Verbindung zu Datenbank herstellen
$g_db = new MySqlDB();
$g_adm_con = $g_db->connect($g_adm_srv, $g_adm_usr, $g_adm_pw, $g_adm_db);

// Daten der aktuellen Organisation einlesen
$g_current_organization = new Organization($g_db, $g_organization);

if($g_current_organization->getValue('org_id') == 0)
{
    // Organisation wurde nicht gefunden
    die('<div style="color: #CC0000;">Error: '. $message_text['missing_orga']. '</div>');
}

// organisationsspezifische Einstellungen aus adm_preferences auslesen
$g_preferences = $g_current_organization->getPreferences();

$message = '';

if($req_mode == 1)
{
    //Datenbank- und PHP-Version prüfen
    if(checkVersions($g_db, $message) == false)
    {
        showPage($message, $g_root_path.'/adm_program/index.php', 'application_view_list.png', 'Übersichtsseite', 2);
    }

    // pruefen, ob ein Update ueberhaupt notwendig ist
    if(isset($g_preferences['db_version']) == false)
    {
        // bei einem Update von Admidio 1.x muss die spezielle Version noch erfragt werden,
        // da in Admidio 1.x die Version noch nicht in der DB gepflegt wurde
        $message = '<img style="vertical-align: top;" src="layout/warning.png" />
                    <strong>Eine Aktualisierung der Datenbank ist erforderlich</strong><br /><br />
                    Bisher wurde eine Version 1.x von Admidio verwendet. Um die Datenbank
                    erfolgreich auf Admidio '. ADMIDIO_VERSION. ' zu migrieren, ist es erforderlich,
                    dass du deine bisherige Version angibst:<br /><br />
                    Bisherige Admidio-Version:&nbsp;
                    <select id="old_version" name="old_version" size="1">
                        <option value="0" selected="selected">- Bitte wählen -</option>
                        <option value="1.4.9">Version 1.4.*</option>
                        <option value="1.3.9">Version 1.3.*</option>
                        <option value="1.2.9">Version 1.2.*</option>
                    </select>';
    }
    elseif(version_compare($g_preferences['db_version'], ADMIDIO_VERSION) != 0 || $g_preferences['db_version_beta'] != BETA_VERSION)
    {
        $message = '<img style="vertical-align: top;" src="layout/warning.png" />
                    <strong>Eine Aktualisierung der Datenbank ist erforderlich</strong>';
    }
    else
    {
        $message = '<img style="vertical-align: top;" src="layout/ok.png" /> 
                    <strong>Eine Aktualisierung ist nicht erforderlich</strong><br /><br />
                    Die Admidio-Datenbank ist aktuell.';
        showPage($message, $g_root_path.'/adm_program/index.php', 'application_view_list.png', 'Übersichtsseite', 2);
    }

    // falls dies eine Betaversion ist, dann Hinweis ausgeben
    if(BETA_VERSION > 0)
    {
        $message .= '<br /><br />Dies ist eine Beta-Version von Admidio.<br /><br />
                    Sie kann zu Stabilitätsproblemen und Datenverlust führen und
                    sollte deshalb nur in einer Testumgebung genutzt werden !';
    }
    showPage($message, 'update.php?mode=2', 'database_in.png', 'Datenbank aktualisieren', 2);
}
elseif($req_mode == 2)
{
    // Updatescripte fuer die Datenbank verarbeiten
    
    if(isset($g_preferences['db_version']) == false)
    {
        if(isset($_POST['old_version']) == false 
        || strlen($_POST['old_version']) > 5
        || $_POST['old_version'] == 0)
        {
            $message   = 'Das Feld <strong>bisherige Admidio-Version</strong> ist nicht gefüllt.';
            showPage($message, 'update.php', 'back.png', 'Zurück', 2);
        }
        $old_version = $_POST['old_version'];
    }
    else
    {
        $old_version = $g_preferences['db_version'];
    }

    // setzt die Ausfuehrungszeit des Scripts auf 2 Min., da hier teilweise sehr viel gemacht wird
    // allerdings darf hier keine Fehlermeldung wg. dem safe_mode kommen
    @set_time_limit(120);

    // vor dem Update die Versionsnummer umsetzen, damit keiner mehr was machen kann
    $temp_version = substr(ADMIDIO_VERSION, 0, 4). 'u';
	$sql = 'UPDATE '. TBL_PREFERENCES. ' SET prf_value = "'. $temp_version. '"
			 WHERE prf_name    = "db_version" ';
	$g_db->query($sql);                

    // erst einmal die evtl. neuen Orga-Einstellungen in DB schreiben
    include('db_scripts/preferences.php');

    $sql = 'SELECT * FROM '. TBL_ORGANIZATIONS;
    $result_orga = $g_db->query($sql);

    while($row_orga = $g_db->fetch_array($result_orga))
    {
        $g_current_organization->setValue('org_id', $row_orga['org_id']);
        $g_current_organization->setPreferences($orga_preferences, false);
    }

    $main_version      = substr($old_version, 0, 1);
    $sub_version       = substr($old_version, 2, 1);
    $micro_version     = substr($old_version, 4, 1);
    $micro_version     = $micro_version + 1;
    $flag_next_version = true;

    // nun in einer Schleife die Update-Scripte fuer alle Versionen zwischen der Alten und Neuen einspielen
    while($flag_next_version)
    {
        $flag_next_version = false;
        
        // in der Schleife wird geschaut ob es Scripte fuer eine Microversion (3.Versionsstelle) gibt
        // Microversion 0 sollte immer vorhanden sein, die anderen in den meisten Faellen nicht
        for($micro_version = $micro_version; $micro_version < 15; $micro_version++)
        {
            // Update-Datei der naechsten hoeheren Version ermitteln
            $sql_file  = 'db_scripts/upd_'. $main_version. '_'. $sub_version. '_'. $micro_version. '_db.sql';
            $conv_file = 'db_scripts/upd_'. $main_version. '_'. $sub_version. '_'. $micro_version. '_conv.php';                
            
            if(file_exists($sql_file))
            {
                // SQL-Script abarbeiten
                $file    = fopen($sql_file, 'r')
                           or showPage('Die Datei <strong>'.$sql_file.'</strong> konnte nicht geöffnet werden.', 'update.php', 'back.png', 'Zurück');
                $content = fread($file, filesize($sql_file));
                $sql_arr = explode(';', $content);
                fclose($file);
    
                foreach($sql_arr as $sql)
                {
                    if(strlen(trim($sql)) > 0)
                    {
                        // Praefix fuer die Tabellen einsetzen und SQL-Statement ausfuehren
                        $sql = str_replace('%PRAEFIX%', $g_tbl_praefix, $sql);
                        $g_db->query($sql);
                    }
                }
                
                $flag_next_version = true;
            }
    
            if(file_exists($conv_file))
            {
                // Nun das PHP-Script abarbeiten
                include($conv_file);
                $flag_next_version = true;
            }
        }

        // keine Datei mit der Microversion gefunden, dann die Main- oder Subversion hochsetzen,
        // solange bis die aktuelle Versionsnummer erreicht wurde
        if($flag_next_version == false
        && version_compare($main_version. '.'. $sub_version. '.'. $micro_version , ADMIDIO_VERSION) == -1)
        {
            if($sub_version == 9)
            {
                $main_version  = $main_version + 1;
                $sub_version   = 0;
            }
            else
            {
                $sub_version   = $sub_version + 1;
            }
            
            $micro_version     = 0;
            $flag_next_version = true;
        }
    }

    // nach dem Update erst einmal bei Sessions das neue Einlesen des Organisations- und Userobjekts erzwingen
    $sql = 'UPDATE '. TBL_SESSIONS. ' SET ses_renew = 1 ';
    $g_db->query($sql);
    
    // nach einem erfolgreichen Update noch die neue Versionsnummer in DB schreiben
	$sql = 'UPDATE '. TBL_PREFERENCES. ' SET prf_value = "'. ADMIDIO_VERSION. '"
			 WHERE prf_name    = "db_version" ';
	$g_db->query($sql);                

	$sql = 'UPDATE '. TBL_PREFERENCES. ' SET prf_value = "'. BETA_VERSION. '"
			 WHERE prf_name    = "db_version_beta" ';
	$g_db->query($sql);                
    
    // globale Objekte aus einer evtl. vorhandenen Session entfernen, 
    // damit diese neu eingelesen werden muessen
    session_name('admidio_php_session_id');
    session_start();
    unset($_SESSION['g_current_organisation']);
    unset($_SESSION['g_preferences']);
    unset($_SESSION['g_current_user']);

    $message   = '<img style="vertical-align: top;" src="layout/ok.png" /> <strong>Die Aktualisierung war erfolgreich</strong><br /><br />
                  Die Admidio-Datenbank ist jetzt auf die Version '. ADMIDIO_VERSION. BETA_VERSION_TEXT. ' aktualisiert worden.<br />
                  Du kannst nun wieder mit Admidio arbeiten.';
    showPage($message, $g_root_path.'/adm_program/index.php', 'application_view_list.png', 'Übersichtsseite', 2);
}

?>