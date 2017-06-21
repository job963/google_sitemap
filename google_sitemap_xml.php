<?php
/**
* Google XML Sitemap
* -----------------------------------------------
* https://github.com/proudcommerce/google_sitemap
* -----------------------------------------------
* by DIATOM Internet & Medien GmbH // 27.07.2009
* by Proud Sourcing GmbH // 19.07.2013
* by Joachim Barthel  // 25.07.2016 & 20.06.2017
* -----------------------------------------------
*
* / install
*
*(1) insert your paths and data below //configuration
*(2) upload file to your webspace (recommended to /bin folder)
*(3) adjust chmod if needed
*(4) open with your browser or run on command line with 'php google_sitemap_xml.php'
*(5) open sitemap.xml and check content
*
* / transfer to google
*
*(1) open www.google.com/webmasters/tools
*(2) log in with your account
*(3) choose website
*(4) "XML-Sitemaps" -> "add Sitemap"
*(5) specify URL of your sitemap.xml
* =====================================================================
*/

// init
$mod_cnf                        = array();
$error                          = array();
$xmlInsert                      = array();
$xmlList                        = array();
$xmlList_cat                    = array();
$xmlList_cms                    = array();
$xmlList_vendor                 = array();
$xmlList_manufacturer           = array();
$xmlList_tags                   = array();
$xmlList_static                 = array();
$xmlList_prod                   = array();
$xmlList_prod_vendor            = array();
$xmlList_prod_manufacturer      = array();

// Shop-Configuration wrapper
class ShopConfig
{
    public function __construct()
    {
        //$sShopDir = './';     // if stored in shop root
        $sShopDir = '../';      // if stored eg. in /bin folder
        include_once( $sShopDir.'config.inc.php');

        /* append sShopURL with / */
        if ((substr($this->sShopURL, -strlen($this->sShopURL)) !== "/")){
                $this->sShopURL .= "/";
        }
    }
}
$shopConfig = new ShopConfig();

// configuration database
$mod_cnf['siteurl']             = $shopConfig->sShopURL;	// shop url (with ending slash!)
$mod_cnf['dbhost']              = $shopConfig->dbHost;		// dbhost
$mod_cnf['dbname']              = $shopConfig->dbName;		// dbname            
$mod_cnf['dbuser']              = $shopConfig->dbUser;		// dbuser
$mod_cnf['dbpass']              = $shopConfig->dbPwd;		// dbpass

// configuration data
$mod_cnf['filepath']            = $shopConfig->sShopDir . '/export/';						// fullpath to sitemaps
$mod_cnf['filename']            = 'sitemap';		    // basename for sitemaps
$mod_cnf['offset']              = 20000;                    // how many product-urls in each sitemap? (max. allowed: 50.000 urls (total, with cats and cms) && max. filesize: 10Mb (uncompressed!))         
$mod_cnf['language']            = 0;                        // shop language id
$mod_cnf['languages']           = getActiveLangIds();       // shop active language ids
$mod_cnf['expired']             = false;                    // true for using also oxseo.oxexpired = 1 (normally only oxseo.oxexpired = 0)

// configuration export
$mod_cnf['export_categories']   = true;                     // export categories?
$mod_cnf['export_products']     = true;                     // export products?
$mod_cnf['export_products_ma']  = false;                     // export manufacturer products?
$mod_cnf['export_products_ve']  = false;                     // export vendor products?
$mod_cnf['export_cms']          = false;                     // export cms pages?
$mod_cnf['export_vendor']       = false;                     // export vendors?
$mod_cnf['export_manufacturer'] = false;                     // export manufacturers?
$mod_cnf['export_tags']         = false;                     // export tags?
$mod_cnf['export_static']       = false;                     // export static seo urls?

/* ----------------- DO NOT EDIT ANYTHING BEHIND THIS LINE ----------------- */

// which run?: script calls with '-c [n]'
// first run (without params) -> call #1
if (isset($_SERVER['argc'][1])){
    if ("-c" != $_SERVER['argv'][1])
    {
        $pcall = 1;
    }
    else {
        $pcall = $_SERVER['argv'][2];
        if (!preg_match("/[\d]/",$pcall))
        {
            die("Illegal call.\n");
        }
    }
} else {
    $pcall = 1;
}

// db connection
$dbh = new PDO('mysql:host='.$mod_cnf['dbhost'].';dbname='.$mod_cnf['dbname'], $mod_cnf['dbuser'], $mod_cnf['dbpass']);

//** get number of needed script-calls, based on active items with valid seo-url. cms and categories will be added to first sitemap automatically.
$cntCalls = ceil(getCountScriptCalls() / $mod_cnf['offset']);

// store cms- and category-data only at first call, further calls are products only
if (1 == $pcall)
{
    foreach ($mod_cnf['languages'] as $iLanguage) {
        $mod_cnf['language'] = $iLanguage;
        
        // get cms data from shop - only at first script-run! (-c 1)
        if($mod_cnf['export_cms'])
        {
            $xmlList_cms = array_merge($xmlList_cms, getCmsSite());
        }

        // get vendor data from shop - only at first script-run! (-c 1)
        if($mod_cnf['export_vendor'])
        {
            $xmlList_vendor = array_merge($xmlList_vendor, getVendors());
        }

        // get manufacturer data from shop - only at first script-run! (-c 1)
        if($mod_cnf['export_manufacturer'])
        {
            $xmlList_manufacturer = array_merge($xmlList_manufacturer, getManufacturers());    
        }

        // get manufacturer data from shop
        if($mod_cnf['export_tags'])
        {
            $xmlList_tags = array_merge($xmlList_tags, getTags());  
        }

        // get static seo data from shop
        if($mod_cnf['export_static'])
        {
            $xmlList_static = array_merge($xmlList_static, getStaticUrls());  
        }    

        // get all categories
        if($mod_cnf['export_categories'])
        {
            $xmlList_cat = array_merge($xmlList_cat, getCategories());
        }

        // get vendor products
        if($mod_cnf['export_products_ve'])
        {
            $xmlList_prod_vendor = array_merge($xmlList_prod_vendor, getProductsVendor());
        }

        // get manufacturer products
        if($mod_cnf['export_products_ma'])
        {
            $xmlList_prod_manufacturer = array_merge($xmlList_prod_manufacture, getProductsManufacturer());    
        }
    }
}

// get products (with offset)
if($mod_cnf['export_products'])
{
    foreach ($mod_cnf['languages'] as $iLanguage) {
        $mod_cnf['language'] = $iLanguage;
        $xmlList_prod = array_merge($xmlList_prod, getProducts($pcall));
    }
}

$dbh = NULL;

// build xml-data and output
$xmlList = array_merge($xmlList_prod, 
                        $xmlList_prod_vendor, 
                        $xmlList_prod_manufacturer, 
                        $xmlList_cat, 
                        $xmlList_cms, 
                        $xmlList_vendor, 
                        $xmlList_manufacturer, 
                        $xmlList_tags, 
                        $xmlList_static);

// create sitemap
$sitemapdata = createSitemap($xmlList);
$smfile = createXmlFile($sitemapdata);

// compress sitemap
#compressSitemapFile($smfile);

// create global sitemaps-index-file (watch sitemaps.org for more infos..)
createSitemapIndex();

//** RECALL SCRIPT
if ($pcall < $cntCalls)
{
    // memory seems to hold list-array-values, maybe depends on local environment
    unset($xmlList,$xmlList_cat,$xmlList_cms,$xmlList_vendor,$xmlList_manufacturer,$xmlList_tags,$xmlList_prod, $xmlList_prod_manufacturer,$xmlList_prod_vendor,$xmlList_static);
   
    // call itself
    $exec = './googlesitemap.sh -c '.($pcall+1);
    //echo "\n".$exec."\n"; //debug
    system($exec);
    exit(0);
}

//** exit all
//echo "\nready.\n";    // debug   
exit(0);

// ** FUNCTIONS

/** get all active and visible categories from database
 * @return array
 */
function getCategories()
{
    global $mod_cnf;
    global $dbh;
    $list = array();
    $sql = "SELECT 
                seo.oxseourl
            FROM
                oxcategories as oxcats
            LEFT JOIN
                oxseo as seo ON (oxcats.oxid=seo.oxobjectid)
            WHERE
                oxcats.oxactive = 1 AND
                oxcats.oxhidden = 0 AND
                seo.oxtype='oxcategory' AND
                seo.oxstdurl NOT LIKE ('%pgNr=%') AND
                ".($mod_cnf['expired'] == true ? '': 'seo.oxexpired = 0 AND ')."
                seo.oxlang = ".$mod_cnf['language']."               
            GROUP BY
                oxcats.oxid;";

    foreach ($dbh->query($sql) as $row)
    {
        $list[] = array(
            'loc'           => $mod_cnf['siteurl'] . $row['oxseourl'],
            'priority'      => '1.0',
            'lastmod'       => date("Y-m-d") . 'T' . date("h:i:s") . '+00:00',
            'changefreq'    => 'weekly',
        );
    }
    return $list;
}

/** get active cms content from database
 * @return array
 */
function getCmsSite()
{
    global $mod_cnf;
    global $dbh;
    $list = array();
    $sql = "SELECT
                seo.oxseourl
            FROM
                oxcontents as content
            LEFT JOIN
                oxseo as seo ON (content.oxid=seo.oxobjectid)
            WHERE
                content.oxactive = 1 AND
                content.oxfolder = ''
                AND seo.oxseourl <> ''
                AND seo.oxseourl NOT LIKE ('%META%')
                ".($mod_cnf['expired'] == true ? '': 'AND seo.oxexpired = 0')."
                AND seo.oxlang = ".$mod_cnf['language']."
            GROUP BY
                content.oxid;";
   
    foreach ($dbh->query($sql) as $row)
    {
        $list[] = array(
            'loc'           => $mod_cnf['siteurl'] . $row['oxseourl'],
            'priority'      => '0.6',
            'lastmod'       => date("Y-m-d") . 'T' . date("h:i:s") . '+00:00',
            'changefreq'    => 'weekly',
        );
    }
    return $list;
}

/** get active vendors from database
 * @return array
 */
function getVendors()
{
    global $mod_cnf;
    global $dbh;
    $list = array();
    $sql = "SELECT
                seo.oxseourl
            FROM
                oxvendor as vendor
            LEFT JOIN
                oxseo as seo ON (vendor.oxid=seo.oxobjectid)
            WHERE
                vendor.oxactive = 1 AND
                seo.oxseourl <> '' AND
                seo.oxtype='oxvendor' AND
                ".($mod_cnf['expired'] == true ? '': 'seo.oxexpired = 0 AND ')."
                seo.oxlang = ".$mod_cnf['language']."
            GROUP BY
                vendor.oxid;";
   
    foreach ($dbh->query($sql) as $row)
    {
        $list[] = array(
            'loc'           => $mod_cnf['siteurl'] . $row['oxseourl'],
            'priority'      => '0.7',
            'lastmod'       => date("Y-m-d") . 'T' . date("h:i:s") . '+00:00',
            'changefreq'    => 'weekly',
        );
    }
    return $list;
}

/** get active manufacturers from database
 * @return array
 */
function getManufacturers()
{
    global $mod_cnf;
    global $dbh;
    $list = array();
    $sql = "SELECT
                seo.oxseourl
            FROM
                oxmanufacturers as manufacturer
            LEFT JOIN
                oxseo as seo ON (manufacturer.oxid=seo.oxobjectid)
            WHERE
                manufacturer.oxactive = 1 AND
                seo.oxseourl <> '' AND
                seo.oxtype='oxmanufacturer' AND
                ".($mod_cnf['expired'] == true ? '': 'seo.oxexpired = 0 AND ')."
                seo.oxlang = ".$mod_cnf['language']."
            GROUP BY
                manufacturer.oxid;";
   
    foreach ($dbh->query($sql) as $row)
    {
        $list[] = array(
            'loc'           => $mod_cnf['siteurl'] . $row['oxseourl'],
            'priority'      => '0.7',
            'lastmod'       => date("Y-m-d") . 'T' . date("h:i:s") . '+00:00',
            'changefreq'    => 'weekly',
        );
    }
    return $list;
}

/** get active manufacturers from database
 * @return array
 */
function getTags()
{
    global $mod_cnf;
    global $dbh;
    $list = array();
    $sql = "SELECT
                seo.oxseourl
            FROM
                oxseo seo
            WHERE
                seo.oxseourl <> '' AND
                seo.oxstdurl LIKE '%=tag%' AND
                seo.oxtype='dynamic' AND
                ".($mod_cnf['expired'] == true ? '': 'seo.oxexpired = 0 AND ')."
                seo.oxlang = ".$mod_cnf['language'];
   
    foreach ($dbh->query($sql) as $row)
    {
        $list[] = array(
            'loc'           => $mod_cnf['siteurl'] . $row['oxseourl'],
            'priority'      => '0.8',
            'lastmod'       => date("Y-m-d") . 'T' . date("h:i:s") . '+00:00',
            'changefreq'    => 'weekly',
        );
    }
    return $list;
}

/** get static seo urls from database
 * @return array
 */
function getStaticUrls()
{
    global $mod_cnf;
    global $dbh;
    $list = array();
    $sql = "SELECT
                seo.oxseourl
            FROM
                oxseo seo
            WHERE
                seo.oxseourl <> '' AND
                seo.oxtype='static' AND
                ".($mod_cnf['expired'] == true ? '': 'seo.oxexpired = 0 AND ')."
                seo.oxlang = ".$mod_cnf['language'];
   
    foreach ($dbh->query($sql) as $row)
    {
        $list[] = array(
            'loc'           => $mod_cnf['siteurl'] . $row['oxseourl'],
            'priority'      => '0.5',
            'lastmod'       => date("Y-m-d") . 'T' . date("h:i:s") . '+00:00',
            'changefreq'    => 'weekly',
        );
    }
    return $list;
}

/** get active products from database with offset
 * @return array
 */
function getProducts($limit)
{
    global $mod_cnf;
    global $dbh;
    $list = array();

    // calculate offset
    $start = $mod_cnf['offset'];
    if (1 == $limit)
    {
        $end = 0;
    }
    else {
        $end = (($limit-1) * $mod_cnf['offset']) - 1;
    }
               
    $sql = "SELECT
                oxart.oxtimestamp,
                seo.oxseourl
            FROM
                oxarticles as oxart
            LEFT JOIN oxobject2category as oxobj2cat
                ON (oxobj2cat.oxobjectid = oxart.oxid)
            LEFT JOIN oxcategories as oxcat
                ON (oxcat.oxid = oxobj2cat.oxcatnid)
            LEFT JOIN oxseo as seo
                ON (oxart.oxid = seo.oxobjectid)
            WHERE
                oxart.oxactive = 1 AND
                oxcat.oxactive = 1 AND
                oxcat.oxhidden = 0 AND
                seo.oxlang = ".$mod_cnf['language']." AND
                seo.oxtype='oxarticle' AND
                ".($mod_cnf['expired'] == true ? '': 'seo.oxexpired = 0 AND ')."
                seo.oxstdurl LIKE ('%cnid=%')
            GROUP BY
                oxart.oxid
            LIMIT ".$start." OFFSET ".$end.";";
                       
    foreach ($dbh->query($sql) as $row)
    {
        $lastmod = $row['oxtimestamp'];
        if ("0000-00-00 00:00:00" == $lastmod)
        {
            $lastmod = date("Y-m-d") . 'T' . date("h:i:s") . '+00:00';
        }
        $lastmod = date("Y-m-d") . 'T' . date("h:i:s") . '+00:00';
        $list[] = array(
            'loc'           => $mod_cnf['siteurl'] . $row['oxseourl'],
            'priority'      => '1.0',
            'lastmod'       => $lastmod,
            'changefreq'    => 'daily',
        );
    }
    return $list;
}

/** get manufacturer product urls from database
 * @return array
 */
function getProductsManufacturer()
{
    global $mod_cnf;
    global $dbh;
    $list = array();
               
    $sql = "SELECT
                oxart.oxtimestamp,
                seo.oxseourl
            FROM
                oxarticles as oxart
            LEFT JOIN oxseo as seo
                ON (oxart.oxid = seo.oxobjectid)
            WHERE
                oxart.oxactive = 1 AND
                seo.oxlang = ".$mod_cnf['language']." AND
                seo.oxtype='oxarticle' AND
                ".($mod_cnf['expired'] == true ? '': 'seo.oxexpired = 0 AND ')."
                seo.oxstdurl LIKE ('%mnid=%')
            GROUP BY
                oxart.oxid";
                       
    foreach ($dbh->query($sql) as $row)
    {
        $lastmod = $row['oxtimestamp'];
        if ("0000-00-00 00:00:00" == $lastmod)
        {
            $lastmod = date("Y-m-d") . 'T' . date("h:i:s") . '+00:00';
        }
        $lastmod = date("Y-m-d") . 'T' . date("h:i:s") . '+00:00';
        $list[] = array(
            'loc'           => $mod_cnf['siteurl'] . $row['oxseourl'],
            'priority'      => '0.9',
            'lastmod'       => $lastmod,
            'changefreq'    => 'daily',
        );
    }
    return $list;
}

/** get vendor product urls from database
 * @return array
 */
function getProductsVendor()
{
    global $mod_cnf;
    global $dbh;
    $list = array();
               
    $sql = "SELECT
                oxart.oxtimestamp,
                seo.oxseourl
            FROM
                oxarticles as oxart
            LEFT JOIN oxseo as seo
                ON (oxart.oxid = seo.oxobjectid)
            WHERE
                oxart.oxactive = 1 AND
                seo.oxlang = ".$mod_cnf['language']." AND
                seo.oxtype='oxarticle' AND
                ".($mod_cnf['expired'] == true ? '': 'seo.oxexpired = 0 AND ')."
                seo.oxstdurl LIKE ('%cnid=v%')
            GROUP BY
                oxart.oxid";
                       
    foreach ($dbh->query($sql) as $row)
    {
        $lastmod = $row['oxtimestamp'];
        if ("0000-00-00 00:00:00" == $lastmod)
        {
            $lastmod = date("Y-m-d") . 'T' . date("h:i:s") . '+00:00';
        }
        $lastmod = date("Y-m-d") . 'T' . date("h:i:s") . '+00:00';
        $list[] = array(
            'loc'           => $mod_cnf['siteurl'] . $row['oxseourl'],
            'priority'      => '0.9',
            'lastmod'       => $lastmod,
            'changefreq'    => 'daily',
        );
    }
    return $list;
}

/** get total number of 'seo-active' products in shop
 * @return integer
 */
function getCountScriptCalls()
{
    global $mod_cnf;
    global $dbh;
    $sql = "SELECT
                oxart.oxid
            FROM
                oxarticles as oxart
            LEFT JOIN oxobject2category as oxobj2cat
                ON (oxobj2cat.oxobjectid = oxart.oxid)
            LEFT JOIN oxcategories as oxcat
                ON (oxcat.oxid = oxobj2cat.oxcatnid)
            LEFT JOIN oxseo as seo
                ON (oxart.oxid = seo.oxobjectid)
            WHERE
                oxart.oxactive = 1 AND
                oxcat.oxactive = 1 AND
                oxcat.oxhidden = 0 AND
                ".($mod_cnf['expired'] == true ? '': 'seo.oxexpired = 0 AND ')."
                seo.oxlang = ".$mod_cnf['language']." AND
                seo.oxtype='oxarticle'
            GROUP BY
                oxart.oxid;";
    return( count($dbh->query($sql)) );
}

/** creates xml data / sitemap-content
 * @return array
 */
function createSitemap($data)
{
    global $mod_cnf;
    $mapdata[] = '<?xml version="1.0" encoding="UTF-8"?>
                  <urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">';

    foreach($data as $key => $val)
    {
        $mapdata[] = '<url><loc>'. $val['loc'] .'</loc><priority>'. $val['priority'] .'</priority><lastmod>'. $val['lastmod'] .'</lastmod><changefreq>'. $val['changefreq'] .'</changefreq></url>';
    }
   
    $mapdata[] = '</urlset>';
    // print sitemap data
    #print_r($mapdata);
    return $mapdata;
}

/** stores xml-file to filesystem
 * @return string
 */
function createXmlFile($smdata)
{
    global $mod_cnf,$pcall;
    $fname = $mod_cnf['filepath'].$mod_cnf['filename'].$pcall.".xml";
    $fp = fopen($fname, "w+");
    fwrite($fp, implode("\n", $smdata));
    fclose($fp);
    return $fname;
}

/** compress sitemap-file: new file is sitemap.gz
 * @return void
 */
function compressSitemapFile($fname)
{
    if (file_exists($fname))
    {
        system("gzip -q -9 ".$fname);
    }
    return;
}

/** append new sitemap to sitemap index
 * @return void
 */
function createSitemapIndex()
{
    global $pcall,$mod_cnf;
    $sitemaps = array();
    $maps = array();
      
    // build xml-content
    $smindex = '<?xml version="1.0" encoding="UTF-8"?>'."\n".'<sitemapindex xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">';
    for ($i=1;$i<=$pcall;$i++)
    {
        $loc = '<loc>'.$mod_cnf['siteurl'].$mod_cnf['filename'].$i.'.xml</loc>';
        $last = '<lastmod>'.date("Y-m-d").'T'.date("H:i:s").'+00:00</lastmod>';
        $sitemaps[] = '<sitemap>'.$loc.$last.'</sitemap>';
    }
    $maps = $smindex . "\n" . implode("\n",$sitemaps);
   
    $sitemapindex = $maps . "\n</sitemapindex>";
   
    // write to file
    @file_put_contents($mod_cnf['filename'].'.xml',$sitemapindex);
    return;
}


/**
 * Return an array of the active language ids
 * 
 * @global ShopConfig $shopConfig
 * @return type
 */
function getActiveLangIds()
{
    global $shopConfig;
    include_once $shopConfig->sShopDir . "/bootstrap.php";
    $oConfig = oxRegistry::getConfig();
    $aLanguageParams = $oConfig->getConfigParam('aLanguageParams');
    $aLangIds = array();
    foreach ($aLanguageParams as $aLanguage) {
        if ($aLanguage['active'] == 1) {
            $aLangIds[] = $aLanguage['baseId'];
        }
    }
    return $aLangIds;
}

?>
