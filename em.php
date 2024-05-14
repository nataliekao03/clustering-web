<?php

require_once 'login.php';
$conn = new mysqli($hn, $un, $pw, $db);
if ($conn->connect_error)
    die($conn->connect_error);

//function to train model
function expectationMaximization($modelname, $scores, $num_clusters, $max_iterations, $tolerance, $conn){
    
    $data = [];
    $lines = explode("\n", $scores);
    foreach ($lines as $line) {
        $values = explode(",", $line);
        if (count($values) >= 2) { // Check if there are at least two elements in $values
            $data[] = [floatval($values[0]), floatval($values[1])];
        }
    }
    

    // Initialize cluster means randomly
    $num_data = count($data);
    $num_dimensions = count($data[0]);
    $means = [];
    for ($i = 0; $i < $num_clusters; $i++) {
        $random_data_index = rand(0, $num_data - 1);
        $means[$i] = $data[$random_data_index];
    }

    // Initialize cluster variances and mixing coefficients
    $variances = array_fill(0, $num_clusters, array_fill(0, $num_dimensions, 0));
    $mixing_coefficients = array_fill(0, $num_clusters, 1 / $num_clusters);

    // EM algorithm iterations
    for ($iteration = 0; $iteration < $max_iterations; $iteration++) {
        // Expectation step - calculate cluster probabilities
        $cluster_probs = [];
        for ($i = 0; $i < $num_data; $i++) {
            $point = $data[$i];
            $prob_sum = 0;
            for ($j = 0; $j < $num_clusters; $j++) {
                $distance = 0;
                for ($k = 0; $k < $num_dimensions; $k++) {
                    $distance += pow($point[$k] - $means[$j][$k], 2);
                }
                $cluster_probs[$i][$j] = exp(-$distance / 2); // Gaussian distribution assumption
                $prob_sum += $cluster_probs[$i][$j];
            }
    
            // Normalize probabilities
            for ($j = 0; $j < $num_clusters; $j++) {
                if ($prob_sum != 0) {
                    $cluster_probs[$i][$j] /= $prob_sum;
                } else {
                    // Handle the case where the denominator is zero
                    $cluster_probs[$i][$j] = 1 / $num_clusters; // Assign equal probabilities if sum is zero
                }
            }
        }

        // Maximization step - update cluster means, variances, and mixing coefficients
        $old_means = $means;
        for ($j = 0; $j < $num_clusters; $j++) {
            for ($k = 0; $k < $num_dimensions; $k++) {
                $numerator_sum = 0;
                $denominator_sum = 0;
                for ($i = 0; $i < $num_data; $i++) {
                    $numerator_sum += $cluster_probs[$i][$j] * $data[$i][$k];
                    $denominator_sum += $cluster_probs[$i][$j];
                }
                $means[$j][$k] = $numerator_sum / $denominator_sum;

                // Update cluster variances
                $variance_sum = 0;
                for ($i = 0; $i < $num_data; $i++) {
                    $variance_sum += $cluster_probs[$i][$j] * pow($data[$i][$k] - $means[$j][$k], 2);
                }
                $variances[$j][$k] = $variance_sum / $denominator_sum;
            }

            // Update mixing coefficients
            $mixing_coefficients[$j] = $denominator_sum / $num_data;
        }

        // Check for convergence
        $max_change = 0;
        for ($j = 0; $j < $num_clusters; $j++) {
            for ($k = 0; $k < $num_dimensions; $k++) {
                $change = abs($old_means[$j][$k] - $means[$j][$k]);
                if ($change > $max_change) {
                    $max_change = $change;
                }
            }
        }
        if ($max_change < $tolerance) {
            break; // Convergence reached
        }
    }    

    // Display means
    echo "Cluster means:<br>";
    foreach ($means as $index => $mean) {
        echo "Cluster " . ($index + 1) . ": (" . implode(", ", $mean) . ")<br>";
    }

    // Display variances
    echo "Cluster variances:<br>";
    foreach ($variances as $index => $variance) {
        echo "Cluster " . ($index + 1) . ": (" . implode(", ", $variance) . ")<br>";
    }

    // Display mixing coefficients
    echo "Mixing coefficients:<br>";
    foreach ($mixing_coefficients as $index => $coefficient) {
        echo "Cluster " . ($index + 1) . ": " . $coefficient . "<br>";
    }

    $means_str = serialize($means);
    $variances_str = serialize($variances);
    $mixing_coefficients_str = serialize($mixing_coefficients);

    // Insert the serialized strings into the database
    $newmodelname = "$modelname.EM";
    $query = "INSERT INTO em (modelname, means, variances, mixing_coefficients) 
          VALUES ('$newmodelname', '$means_str', '$variances_str', '$mixing_coefficients_str')";
    $result = $conn->query($query);

    if (!$result)
        die("Insertion failed: " . $conn->error);


    $emid = $conn->insert_id;
    $query = "UPDATE scores SET emid = $emid WHERE modelname = '$modelname'";
    $result = $conn->query($query);
    if (!$result)
        die("Update failed: " . $conn->error);


    return [$means, $variances, $mixing_coefficients];
}

// Function to test the trained EM model with new dataset
function em($scores, $selected_trainedmodel, $conn) {
    // Parse new scores

    $data = [];
    $lines = explode("\n", $scores);
    foreach ($lines as $line) {
        $values = explode(",", $line);
        $data[] = [floatval($values[0]), floatval($values[1])];
    }

    $query = "SELECT means, variances, mixing_coefficients FROM em WHERE modelname='$selected_trainedmodel'";

    $result = mysqli_query($conn, $query);
    if (!$result) {
        die("Database query failed: " . mysqli_error($conn));
    }

    if (mysqli_num_rows($result) == 0) {
        die("No data for model '$selected_trainedmodel'");
    }

    $row = mysqli_fetch_assoc($result);

    $means = unserialize($row['means']);
    $variances = unserialize($row['variances']);
    $mixing_coefficients = unserialize($row['mixing_coefficients']);

    // Initialize variables
    $num_clusters = count($means);
    $num_dimensions = count($means[0]);
    $num_new_data = count($means);
    $cluster_probs = [];

    // Iterate over new data
    for ($i = 0; $i < $num_new_data; $i++) {
        $point = $means[$i];
        $prob_sum = 0;

        // Calculate cluster probabilities for each point
        for ($j = 0; $j < $num_clusters; $j++) {
            $distance = 0;
            for ($k = 0; $k < $num_dimensions; $k++) {
                $distance += pow($point[$k] - $means[$j][$k], 2);
            }
            $cluster_probs[$i][$j] = exp(-$distance / 2); // Gaussian distribution assumption
            $prob_sum += $cluster_probs[$i][$j];
        }

        // Normalize probabilities
        for ($j = 0; $j < $num_clusters; $j++) {
            if ($prob_sum != 0) {
                $cluster_probs[$i][$j] /= $prob_sum;
            } else {
                // Handle the case where the denominator is zero
                $cluster_probs[$i][$j] = 1 / $num_clusters; // Assign equal probabilities if sum is zero
            }
        }
    }

    // Display results
    echo "<h2>Cluster Probabilities for Each Data Point:</h2>";
    echo "<table border='1'>";
    echo "<tr><th>Data Point</th>";
    for ($j = 0; $j < $num_clusters; $j++) {
        echo "<th>Cluster " . ($j + 1). "</th>";
    }
    echo "</tr>";
    for ($i = 0; $i < $num_new_data; $i++) {
        echo "<tr><td>Data Point $i</td>";
        for ($j = 0; $j < $num_clusters; $j++) {
            echo "<td>" . number_format($cluster_probs[$i][$j], 6) . "</td>"; // Display probabilities with 6 decimal places
        }
        echo "</tr>";
    }
    echo "</table>";

    // Return cluster probabilities for each new data point
    return $cluster_probs;
}


?>