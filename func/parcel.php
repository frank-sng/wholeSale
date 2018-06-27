<?php
// retrieve paremeters that are needed
$zipcode = $_GET['z'];
// Validate if the zipcode is valid
if (preg_match('/^\d{5}$/', $zipcode)) {
    if (is_dir("../deals/$zipcode")) {
        // By given zipcode return all the parcels from a local csv file
        $array = get_parcels_from_csv($zipcode);
        // start and end error handling
        if ($_GET['s'] != null) {
            $start = $_GET['s'];
        } else {
            $start = 0;
        }
        if ($_GET['e'] != null) {
            $end = $_GET['e'];
        } else {
            $end = count($array);
        }
        // Loop through all the parcels and get the properties that are "not occupied"
        for ($i = $start; $i <= $end; $i++) {
            // Get the property details
            $results = get_property_details(preg_replace('/\R/', '', $array[$i]));
            // check if the property is occupied or not
            if ($results[0]['Address'] == $results[0]['Owner Address']) {
                echo $i . " - Occupied ;)<br>";
            } else if ($results[0]['Address'] != $results[0]['Owner Address']) {
                echo $i . " - WholeSale - " . $results[0]['ID'] . "<br>";
                $data = $results[0];
                place_deals_into_file($zipcode, $data);
            }
            if (count($array) <= $i) {
                header('Location: ../index.php?message=complete_zipcode');
                die();
            }
            flush();
        }
    } else {
        header('Location: ../index.php?message=file_missing');
        die();
    }
} else {
    header('Location: ../index.php?message=invalid_zip');
    die();
}

// Put in a property Parcel ID and get the property details from http://multcoproptax.org/ Multnomah County
// Make sure to be logged into the site.
function get_property_details($par) {
    $details = [];
    // API url to call to get the page content
    $url = "http://multcoproptax.org/property.asp?PropertyID=$par";
    // Run function to get content from a web page
    $result = get_web_page($url);
    // Get the right content from the page
    $string = $result['content'];
    // Load new document and load in the html
    $dom = new DOMDocument;
    @$dom->loadHTML($string);
    // Get to the starting point of all data
    $data = $dom->childNodes->item(1)->childNodes->item(1)->childNodes->item(1)->getElementsByTagName('table')->item(5)->getElementsByTagName('tr');
    /*
        table = 8 = Land Information
        table = 7 = Sales Information
        table = 6 = Property Description
        table = 5 =  Search Results for
    */
    // Get all the data that is needed
    $parcel_id = $data->item(2)->getElementsByTagName('td')->item(1)->textContent;
    $parcel_addr = $data->item(4)->getElementsByTagName('td')->item(1)->childNodes->item(0)->textContent . ", " . $data->item(4)->getElementsByTagName('td')->item(1)->childNodes->item(2)->textContent;
    $parcel_owner_name = $data->item(2)->getElementsByTagName('td')->item(0)->textContent;
    $parcel_owner_addr = $data->item(4)->getElementsByTagName('td')->item(0)->childNodes->item(0)->textContent . ", " . $data->item(4)->getElementsByTagName('td')->item(0)->childNodes->item(2)->textContent;
    $parcel_map_link = $data->item(10)->getElementsByTagName('td')->item(0)->getElementsByTagName('a')->item(0)->getAttribute('href');
    // Put all data into an array
    $details[] = [
        'ID' => $parcel_id,
        'Address' => $parcel_addr,
        'Owner Name' => $parcel_owner_name,
        'Owner Address' => $parcel_owner_addr,
        'Map Link' => $parcel_map_link
    ];
    // Return data as array
    return $details;
}
// Get the web content from url and return it in an array
function get_web_page( $url, $cookiesIn = 'ASPSESSIONIDCSCRQAAS=LCBLHFEDMDLBPFDNLBDEGLHD; ASPSESSIONIDAQDRRAAT=ENEMBOODLGEHGLFOJFFFFKMF; ASPSESSIONIDCQBSQBBS=HJDLNJDDKPPCEOIJALJGIBGB; ASPSESSIONIDASBTRAAT=ANJNJCODHNELFAKFPILAAJCP; ASPSESSIONIDCSDRQBAT=BIMIFEHAHIPEAKGIAOMBAHGJ; ASPSESSIONIDCSAQSAAS=NCLOBNBBJCPNGCLGNAOEKNDH; ASPSESSIONIDAQDRQABS=PKICKOGCGOIMADCLDACEEKLC; ASPSESSIONIDASAQSABS=LEBNNIGACJGIIGOIKBFBBPAL; ASPSESSIONIDCSARTBAS=NPNLJBBBNDJFPFJGOANHDMHD; ASPSESSIONIDASBRRAAT=OFNLFKLBEOIAFOOFPBEJFNOJ' ){
    $options = array(
        CURLOPT_RETURNTRANSFER => true,     // return web page
        CURLOPT_HEADER         => true,     //return headers in addition to content
        //CURLOPT_FOLLOWLOCATION => true,     // follow redirects
        CURLOPT_ENCODING       => "",       // handle all encodings
        //CURLOPT_AUTOREFERER    => true,     // set referer on redirect
        //CURLOPT_CONNECTTIMEOUT => 120,      // timeout on connect
        //CURLOPT_TIMEOUT        => 120,      // timeout on response
        CURLOPT_MAXREDIRS      => 10,       // stop after 10 redirects
        //CURLINFO_HEADER_OUT    => true,
        //CURLOPT_SSL_VERIFYPEER => true,     // Validate SSL Certificates
        CURLOPT_HTTP_VERSION   => CURL_HTTP_VERSION_1_1,
        CURLOPT_COOKIE         => $cookiesIn
    );

    $ch = curl_init( $url );
    curl_setopt_array( $ch, $options );
    $rough_content = curl_exec( $ch );
    $err     = curl_errno( $ch );
    $errmsg  = curl_error( $ch );
    $header  = curl_getinfo( $ch );
    curl_close( $ch );

    $header_content = substr($rough_content, 0, $header['header_size']);
    $body_content = trim(str_replace($header_content, '', $rough_content));
    $pattern = "#Set-Cookie:\\s+(?<cookie>[^=]+=[^;]+)#m"; 
    preg_match_all($pattern, $header_content, $matches); 
    $cookiesOut = implode("; ", $matches['cookie']);

    $header['errno']   = $err;
    $header['errmsg']  = $errmsg;
    $header['headers']  = $header_content;
    $header['content'] = $body_content;
    $header['cookies'] = $cookiesOut;
    return $header;
}
// Get Parcel ids from CSV file
function get_parcels_from_csv($zip) {
    // create the path to get the parcel id's
    $path = "../deals/$zip/res_$zip.csv";
    // get the content from the file
    $content = file_get_contents($path);
    // put csv data into an array to work with
    $parcels = explode("\n", $content);
    // return all parcels in an array
    return $parcels;
}
// all the parcels place into a csv file to look at
function place_deals_into_file($zip, $data) {
    // break up the array
    $id = preg_replace('/[^(\x20-\x7F)]*/', '',$data['ID']);
    $addr = preg_replace('/[^(\x20-\x7F)]*/', '',$data['Address']);
    $oName = preg_replace('/[^(\x20-\x7F)]*/', '',$data['Owner Name']);
    $oAddr = preg_replace('/[^(\x20-\x7F)]*/', '',$data['Owner Address']);
    $mapLink = preg_replace('/[^(\x20-\x7F)]*/', '',$data['Map Link']);
    // check if directory exists
    if (is_dir("../deals/$zip")) {
        // check if file exists
        if (!file_exists("../deals/$zip/deals_$zip.csv")) {
            // create file if doesn't exist
            fopen("../deals/$zip/deals_$zip.csv", 'c');
            // check if file was created
            sleep(1);
            place_deals_into_file($zip, $data);
        } else {
            if (!filesize("../deals/$zip/deals_$zip.csv")) {
                // write the first lines in a file
                file_put_contents("../deals/$zip/deals_$zip.csv", "Parcel ID, Address, Owner Name, Owner Address, Map Link\n $id,\"$addr\",\"$oName\",\"$oAddr\",$mapLink \n", FILE_APPEND);
            } else {
                // keep on placing data into file
                file_put_contents("../deals/$zip/deals_$zip.csv", "$id,\"$addr\",\"$oName\",\"$oAddr\",$mapLink \n" ,FILE_APPEND);
            }
        }
    } else {
        // create a directory 
        mkdir("../deals/$zip", 0777, true);
        // check if file exists
        if (!file_exists("../deals/$zip/deals_$zip.csv")) {
            // create file if doesn't exist
            fopen("../deals/$zip/deals_$zip.csv", 'c');
            // check if file was created
            sleep(1);
            place_deals_into_file($zip, $data);
        } else {
            if (!filesize("../deals/$zip/deals_$zip.csv")) {
                // write the first lines in a file
                file_put_contents("../deals/$zip/deals_$zip.csv", "Parcel ID, Address, Owner Name, Owner Address, Map Link\n $id,\"$addr\",\"$oName\",\"$oAddr\",$mapLink \n", FILE_APPEND);
            } else {
                // keep on placing data into file
                file_put_contents("../deals/$zip/deals_$zip.csv", "$id,\"$addr\",\"$oName\",\"$oAddr\",$mapLink \n" ,FILE_APPEND);
            }
        }
    }
}

/*

- 97233 - 8400~
3000 DONE | http://localhost/wholeSale/func/parcel.php?z=97233&s=1400

*/