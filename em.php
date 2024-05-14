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
        $data[] = [floatval($values[0]), floatval($values[1])];
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
    $query = "INSERT INTO em (modelname, means, variances, mixing_coefficients) 
          VALUES ('$modelname', '$means_str', '$variances_str', '$mixing_coefficients_str')";
    $result = $conn->query($query);

    if (!$result)
        die("Insertion failed: " . $conn->error);

    $newmodelname = "$modelname.EM";

    $emid = $conn->insert_id;
    $query = "UPDATE scores SET emid = $emid WHERE modelname = '$modelname'";
    $result = $conn->query($query);
    if (!$result)
        die("Update failed: " . $conn->error);


    return [$means, $variances, $mixing_coefficients];
}

// Function to test the trained EM model with new dataset
function em($modelname, $new_scores, $trained_means, $trained_variances, $trained_mixing_coefficients, $tolerance, $conn) {
    // Parse new scores
    $new_data = [];
    $query = "SELECT means, variances, mixing_coefficients FROM em WHERE modelname='$modelname";
    $result = mysqli_query($conn, $query);
    if (!$result) {
        die("Database query failed.");
    }
    while ($row = mysqli_fetch_assoc($result)) {
        $new_data[] = [floatval($row['column1']), floatval($row['column2'])];
    }

    // Initialize variables
    $num_clusters = count($trained_means);
    $num_dimensions = count($trained_means[0]);
    $num_new_data = count($new_data);
    $cluster_probs = [];

    // Iterate over new data
    for ($i = 0; $i < $num_new_data; $i++) {
        $point = $new_data[$i];
        $prob_sum = 0;

        // Calculate cluster probabilities for each point
        for ($j = 0; $j < $num_clusters; $j++) {
            $distance = 0;
            for ($k = 0; $k < $num_dimensions; $k++) {
                $distance += pow($point[$k] - $trained_means[$j][$k], 2);
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

    // Display results or return them as needed
    return $cluster_probs; // Return cluster probabilities for each new data point
}


?>