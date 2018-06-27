<!-- This is the Header -->
<?php
include('content/header.php');
?>



<div id="form_div">
    <form id="zip_form" action="func/zipcode.php" method="GET">
        <?php
            if ($_GET['message'] == 'invalid') {
                echo "<div id='error-message'><span>Please Enter a Valid Zipcode!</span></div>";
            }
        ?>
        <div id="form_body">
            <input id="inp_zip" placeholder="Zipcode" type="text" name="zip">
        </div>
        <div id="form_footer">
            <input id="sub_btn" type="submit" value="Submit">
        </div>
    </form>
</div>

<!-- This is the Footer -->
<?php
include('content/footer.php');
?>