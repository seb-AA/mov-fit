 <?php
 
 session_start();
 
// Check if 'nummy_cookie' cookie is not set
if (!isset($_COOKIE['nummy_cookie'])) {
    // Generate a unique ID for 'nummy_cookie'
    $nummy_cookie = uniqid();

    // Set the 'nummy_cookie' cookie with the generated unique ID, valid for 30 days
    setcookie('nummy_cookie', $nummy_cookie, time() + (86400 * 30), "/");
} else {
    // Retrieve the value of 'nummy_cookie' from the existing cookie
    $nummy_cookie = $_COOKIE['nummy_cookie'];
}
?>
<!DOCTYPE html>
<html>
    <meta name="viewport" content="width=device-width, initial-scale=1">
<head>
    
    <style>
    /* General styles */
    body {
        font-family: Helvetica, sans-serif;
        background-color: #f4f4f4;
        color: #f00;
        padding: 20px;
    }

    .button {
        height: 30px;
        padding: 10px 20px;
        border-radius: 5px;
        cursor: pointer;
    }

    textarea {
    width: 100%; /* Set the width to 100% of the container */
    max-width: 800px; /* Set a maximum width */
    min-width: 200px; /* Set a minimum width */
    height: 100px;
    font-size: 14px;
    padding: 5px;
    box-sizing: border-box; /* Include padding and border in the width */
    resize: vertical; /* Allow vertical resizing */
}


    /* Styles for the instructions pop-up */
    .instructions-popup {
        display: none;
        position: fixed;
        top: 50%;
        left: 50%;
        transform: translate(-50%, -50%);
        background-color: #fff;
        padding: 20px;
        box-shadow: 0 0 10px rgba(0, 0, 0, 0.2);
        max-width: 400px;
        z-index: 9999;
    }

    /* Styles for the advanced options modal */
    .modal {
        display: none;
        position: fixed;
        top: 50%;
        left: 50%;
        transform: translate(-50%, -50%);
        background-color: #fff;
        padding: 20px;
        box-shadow: 0 0 10px rgba(0, 0, 0, 0.2);
        width: auto;
        min-width: 200px; /* Adjust as needed */
        z-index: 9999;
    }

    .dropdown {
        width: 100%;
        box-sizing: border-box;
        /* Add other dropdown styles as needed */
    }

    @media (max-width: 768px) {
        form {
            font-size: 2em;
            margin: 1em;
        }
    }
    
    .close {
    position: absolute; /* Position the close button absolutely within the modal */
    top: 10px;          /* Set the distance from the top of the modal */
    right: 10px;        /* Set the distance from the right of the modal */
    cursor: pointer;    /* Change the cursor to a pointer when hovering over the close button */
    font-size: 18px;    /* Set the font size */
    color: #333;        /* Set the text color */
}

.close:hover {
    color: #f00;        /* Change the text color when hovering */
}

button:disabled {
    background-color: #ccc; /* Light grey background */
    cursor: not-allowed; /* Cursor indicating not clickable */
}

.centered-container {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            min-height: 100vh; /* Full viewport height */
        }

.hidden {
        display: none; /* This class hides an element */
    }
    
</style>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bulma@0.9.4/css/bulma.min.css">
<script type='text/javascript'>
    // Index Functions
    function toggleInstructions() {
        var popup = document.getElementById("instructions-popup");
        popup.style.display = popup.style.display === "block" ? "none" : "block";
    }

    function toggleAdvancedOptions() {
        var popup = document.getElementById("advanced-options-modal");
        popup.style.display = popup.style.display === "block" ? "none" : "block";
    }
    
    function toggleClaudeModal() {
        var modal = document.getElementById("claude-modal");
        modal.style.display = modal.style.display === "block" ? "none" : "block";
    }

    function saveAdvancedOptions(event) {
        event.preventDefault();
        let intensityLevel = document.getElementById('intensity-level').value;
        let workoutType = document.getElementById('workout-type').value;
        let mainForm = document.querySelector('form[action="server.php"]');
        mainForm.appendChild(createHiddenField('intensity-level', intensityLevel));
        mainForm.appendChild(createHiddenField('workout-type', workoutType));
        document.getElementById('advanced-options-modal').style.display = 'none';
    }

    window.onload = function() {
        document.getElementById('searchButton').addEventListener('click', function(event) {
            event.preventDefault();
            var mediaType = document.getElementById('selectedMediaType').value;
            if (mediaType !== 'movie' && mediaType !== 'tv') {
                alert("Invalid media type. Please select either a movie or TV show.");
                return;
            }

            var searchTerm = mediaType === 'movie' ? document.getElementById('searchMovieTerm').value : document.getElementById('searchTVShowTerm').value;
            var xhr = new XMLHttpRequest();
            xhr.open('POST', 'search.php', true);
            xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
            xhr.onreadystatechange = function() {
                if (this.readyState === XMLHttpRequest.DONE && this.status === 200) {
                    var results = JSON.parse(this.responseText);
                    var dropdown = document.getElementById('movie-results');
                    if (results.results.length === 0) {
                        dropdown.classList.add('hidden');
                    } else {
                        dropdown.classList.remove('hidden');
                    }
                    dropdown.innerHTML = '';
                    results.results.forEach(function(result) {
                        var option = document.createElement('option');
                        var title = mediaType === 'movie' ? result.title : result.name;
                        var year = mediaType === 'movie' ? result.release_date.split('-')[0] : result.first_air_date.split('-')[0];
                        var tmdbId = result.id;
                        option.value = tmdbId;
                        option.textContent = title + ' (' + year + ')';
                        dropdown.appendChild(option);
                    });
                    document.getElementById('downloadButton').disabled = false;
                }
            };
            xhr.send('mediaType=' + mediaType + '&searchTerm=' + searchTerm);
        });

        document.getElementById('movie').addEventListener('change', function() {
            document.getElementById('selectedMediaType').value = 'movie';
            document.getElementById('movieSearch').style.display = 'block';
            document.getElementById('tvSearch').style.display = 'none';
        });
        document.getElementById('tv').addEventListener('change', function() {
            document.getElementById('selectedMediaType').value = 'tv';
            document.getElementById('tvSearch').style.display = 'block';
            document.getElementById('movieSearch').style.display = 'none';
        });

        document.getElementById('downloadButton').addEventListener('click', function(event) {
            event.preventDefault();
            var selectedTMDBId = document.getElementById('movie-results').value;
            var selectedMediaType = document.getElementById('selectedMediaType').value;
            var seasonNumber = selectedMediaType === 'tv' ? document.getElementById('tvSeason').value : null;
            var episodeNumber = selectedMediaType === 'tv' ? document.getElementById('tvEpisode').value : null;

            alert('Processing subtitle download...');

            var xhr = new XMLHttpRequest();
            xhr.open('POST', 'download.php', true);
            xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
            xhr.onload = function() {
                if (xhr.status === 200) {
                    alert('Subtitles downloaded successfully to the server.');
                } else {
                    alert('Error in downloading subtitles.');
                }
            };
            xhr.send('selectedTMDBId=' + encodeURIComponent(selectedTMDBId) + 
                     '&mediaType=' + encodeURIComponent(selectedMediaType) +
                     '&seasonNumber=' + encodeURIComponent(seasonNumber) +
                     '&episodeNumber=' + encodeURIComponent(episodeNumber));
        });
    };

    window.addEventListener('DOMContentLoaded', function() {
        document.querySelector('.close').addEventListener('click', function() {
            document.querySelector('.modal').style.display = 'none';
        });
    });

    var promptText = "Divide this subtitle file into the major acts or movements of the full video, include a descriptive name and 4-5 sentence description for each act, and the timecodes marking the beginning and end. Include 1 unique act per 15 minutes.";

    function copyPromptToClipboard() {
        var textarea = document.createElement('textarea');
        textarea.value = promptText;
        document.body.appendChild(textarea);
        textarea.select();
        document.execCommand('copy');
        document.body.removeChild(textarea);
        alert('Prompt copied to clipboard!');
    }
</script>




</head>
<body>
  <div class="centered-container content is-medium">
    <body style="background-color:#fff2ff;">

    <div>
<br>
        
        <h1>Create your Workout</h1>

        
        
        
        
        
        
        <div class="box">
        <form id="mediaForm" action="search.php" method="post" enctype="multipart/form-data">
            <p><strong>Type of Media: 
            <input type="radio" id="movie" name="mediaType" value="movie">
<label for="movie">Movie</label>
<input type="radio" id="tv" name="mediaType" value="tv">
<label for="tv">TV Show</label>
            </p>
            <input type="hidden" id="selectedMediaType" name="selectedMediaType">
            <div id="movieSearch">
                <p>Movie Title: <input type="text" id="searchMovieTerm" name="searchMovieTerm"></p>
            </div>
            <div id="tvSearch" style="display: none;">
                <p>TV Show Title: <input type="text" id="searchTVShowTerm" name="searchTVShowTerm"></p>
                <p>Season: <input type="number" id="tvSeason" name="tvSeason" min="1" class="small-input"></p>
                <p>Episode: <input type="number" id="tvEpisode" name="tvEpisode" min="1" class="small-input"></p>
            </div>
            <select id="movie-results" name="selectedMovie" class="hidden"></select><br>
            <div>
                <button type="submit" id="searchButton" class="button is-link">Search</button>&nbsp;&nbsp;&nbsp;<button type="button" class="button is-info" id="downloadButton" disabled>Confirm Selection</button><br><br>
            </div></strong>
        </form>
        </div>
    
    
    
    
    
    
            <div class="box">
            <form action="server.php" method="post">
            <input type="hidden" name="nummy_cookie" value="<?php echo $nummy_cookie; ?>">
            <strong>Workout Options:</strong>
<p>Intensity Level: 
    <select id="intensity-level" name="intensity-level">
        <option value="low">Low</option>
        <option value="medium">Medium</option>
        <option value="high">High</option>
    </select>
</p>
<p>Workout Type:
    <select id="workout-type" name="workout-type">
        <option value="bodyweight">Bodyweight</option>
        <option value="cardio">Cardio</option>
        <option value="stretching">Stretching</option>
    </select>
</p><p>custom specifications</p> <input type="text" id="workoutspecs" name="workoutspecs">
    
    <br><br>
            <button type="submit" class="button is-link">Create</button>
        </form>
    </div>
  </div>
</body>
</html> 