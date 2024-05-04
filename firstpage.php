<?php
require_once 'a6_login.php';

$conn = new mysqli($hn, $un, $pw, $db);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

session_start();

// Prevent session fixation
if (!isset($_SESSION['initiated'])) {
    session_regenerate_id();
    $_SESSION['initiated'] = 1;
}

// Display main page
displayMainPage();

// Handle form submission
submitForm();

// Close database connection
$conn->close();

// Display main page
function displayMainPage(){
?>
<html>
<head>
    <title>Main Page</title>
</head>
<body>
    <form method="post" action="a6_firstpage.php">
        <table border="0" cellpadding="2" cellspacing="10">
            <th colspan="2" align="center">Main Page</th>
            <tr><td>Student's Name</td>
                <td><input type="text" maxlength="255" name="name"></td></tr>
            <tr><td>Student ID</td>
                <td><input type="number" maxlength="9" name="id"></td></tr>
            <tr><td colspan="2" align="center"><input type="submit" name="search" value="Search"></td></tr>
        </table>
    </form>
</body>
</html>
<?php
}

// Handle form submission
function submitForm(){
    if(isset($_POST['search'])){
        echo "Form submitted"; // Debugging statement
        $name = sanitizeString($_POST['name']);
        $id = sanitizeString($_POST['id']);
        
        if(searchStudent($name, $id)){
            $advisor = searchAdvisor($id);
            if ($advisor) {
                echo "<h3>Advisor Information:</h3>";
                echo "Advisor Name: " . $advisor["name"] . "<br>";
                echo "Email: " . $advisor["email"] . "<br>";
                echo "Phone Number: " . $advisor["phoneNum"] . "<br>";
            } else {
                echo "No advisor found for the given student ID.";
            }
        } else {
            echo "Student not found.";
        }
    }
}

function searchStudent($name, $studentID){
    global $conn;
    $stmt = $conn->prepare('SELECT * FROM credentials WHERE name=? AND id=?');
    $stmt->bind_param('si', $name, $studentID);
    $stmt->execute();
    $result = $stmt->get_result();
    return $result->fetch_array(MYSQLI_NUM);
    $exists = $result->num_rows > 0;
    $stmt->close();
    return $exists;

}

function searchAdvisor($studentID){
    global $conn;
    $stmt = $conn->prepare('SELECT name, phoneNum, email FROM advisors WHERE lower_bound_id <= ? AND upper_bound_id >= ?');
    $stmt->bind_param('ii', $studentID, $studentID);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows > 0) {
        return $result->fetch_assoc();
    } else {
        return null;
    }
}

// Sanitize input
function sanitizeString($var) {
    $var = stripslashes($var);
    $var = strip_tags($var);
    $var = htmlentities($var);
    return $var;
}
?>
