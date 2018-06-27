<?php
// Get the Value Inputed
$zipcode = $_GET['zip'];
// Check if the value is a valid zipcode
if (preg_match('/^\d{5}$/', $zipcode)) {
    // Run Function if input is Valid
    $property_array = get_all_properties_from_zipcode($zipcode);
} else {
    header('Location: ../index.php?message=invalid');
    die();
}
// Download CSV File when 'csvdownload' is requested
if(array_key_exists('csvdownload',$_POST)){
    convert_to_csv($property_array, 'properties.csv', ',');
}
// Get array of all the properties in a specific ZIPCODE
function get_all_properties_from_zipcode($zipcode) {
    $properties = [];
    // API Key to call json file with all the properties within a zipcode
    $api = "https://makeloveland.com/us/$zipcode/list.json?page="; // ?page= { PAGE NUMBER - DIVIDENT BY 200 }
    // Get Content From JSON URL
    $string = file_get_contents($api);
    // Decode json into an array
    $json = json_decode($string);
    // Get the limit that the a request spits out
    $limit = $json->limit;
    // Divide the total amount of properties by how much are spit out and round up
    $property_count = ceil(($json->count/$limit));
    // Loop through all the PAGES of properties in the ZipCode
    for ($a = 1; $a <= $property_count; $a++) { // ===========> Change to number for testing <============
        // Curtain URL to call to get parcels
        $url = $api.$a;
        // Get the parcels from certain url
        $string_data = file_get_contents($url);
        // Decode JSON file into an array
        $data = json_decode($string_data);
        // Get the limit that the request spits out
        $data_limit = count($data->table);
        // Loop through all the parcels in a given url and add them to an array
        for ($b = 0; $b < $data_limit; $b++) {
            // Get the details from a certain parcel
            $parcel_id = $data->table[$b]->parcel->parcelnumb;
            $parcel_addr = $data->table[$b]->parcel->address;
            $parcel_zone = $data->table[$b]->parcel->zoning;
            $parcel_owner = $data->table[$b]->parcel->owner;
            // Place the data into an array to later return
            $properties[] = [
                'parcel_id'     => $parcel_id,
                'parcel_owner'  => $parcel_owner,
                'parcel_addr'   => $parcel_addr,
                'parcel_zone'   => $parcel_zone
            ];
        }
    }
    // Return array with all the properties/parcels
    return $properties;
}
// Display Values in a table
function display_results($data) {
    // Get the array into a variable
    $array = $data;

    //print_r($array[0]['parcel_id']);

    for ($a = 0; $a < count($array); $a++) {
        echo 
        '<tr id="valid">'.
            "<th>".($a+1)."</th>".
            "<th>".$array[$a]['parcel_id']."</th>".
            "<th>".$array[$a]['parcel_owner']."</th>".
            "<th>".$array[$a]['parcel_addr']."</th>".
            "<th>".$array[$a]['parcel_zone']."</th>".
        '<tr>';
    }
}
// Convert Array of Data to CSV DOWNLOADABLE
function convert_to_csv($input_array, $output_file_name, $delimiter) {
    $temp_memory = fopen('php://memory', 'w');
    // loop through the array
    foreach ($input_array as $line) {
        // use the default csv handler
        fputcsv($temp_memory, $line, $delimiter);
    }

    fseek($temp_memory, 0);
    // modify the header to be CSV format
    header('Content-Type: application/csv');
    header('Content-Disposition: attachement; filename="' . $output_file_name . '";');
    // output the file to be downloaded
    fpassthru($temp_memory);
}
?>

<!-- This is the Header -->
<?php
include('../content/header.php');
?>

<div id="content">
    <div id="table">
        <table id="data_table">
            <thead>
                <tr>
                    <th>#</th>
                    <th>ID</th>
                    <th>OWNER</th>
                    <th>ADDRESS</th>
                    <th>ZONE</th>
                </tr>
            </thead>

            <tbody>
                <tr></tr>
                <?php display_results($property_array);?>
            </tbody>
        </table>
    </div>
</div>

<form method="post">
    <input type="submit" name="csvdownload" id="csvdownload" value="Download CSV!" /><br/>
</form>

<!-- This is the Footer -->
<?php
include('../content/footer.php');
?>