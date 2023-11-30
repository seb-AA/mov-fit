<?php

$nummy_cookie = $_COOKIE['nummy_cookie'] ?? 'default_filename';
?>
<!DOCTYPE html>
<html>
    <meta name="viewport" content="width=device-width, initial-scale=1">
<head>
    <title>Movie Workout</title>
    <style>
        /* Define your color theme here */
        body {
            color: red;
            text-align: center;
            vertical-align: center;
        }

        @media (max-width: 768px) {
        .your-element-class {
            width: 100%;
        }
            }
            
.container {
  display: flex;
  justify-content: center;
}

.tracker {
  margin: 20px;
}

.numbers {
  display: flex;
}

.number {
  padding: 10px;
  cursor: pointer;
}

.number.selected {
  background-color: #ff4d4d;
}

.box {
    display: center;
}

.master {
    justify-content: center;
    display: inline-block;
    width: 80%;
}

/* Responsive Design */
@media (max-width: 768px) {
  .tracker {
    margin: 10px;
  }

  .number {
    padding: 2px; /* Smaller padding on smaller screens */
    font-size: 0.7em; /* Smaller font size */
  }

  /* Adjusting the layout for small screens */
  .container {
    flex-direction: column; /* Stack elements vertically on small screens */
  }
}

@media (min-width: 769px) and (max-width: 1024px) {
  .number {
    padding: 8px; /* Medium padding for medium screens */
    font-size: 1em; /* Medium font size */
  }
}
    </style>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bulma@0.9.4/css/bulma.min.css">
    <script type='text/javascript'>
    function getParameterByName(name, url) {
        if (!url) url = window.location.href;
            name = name.replace(/[\[\]]/g, '\\$&');
        var regex = new RegExp('[?&]' + name + '(=([^&#]*)|&|#|$)'),
            results = regex.exec(url);
        if (!results) return null;
        if (!results[2]) return '';
        return decodeURIComponent(results[2].replace(/\+/g, ' '));
}

    
        // Workout Functions
        let exerciseData;
        let currentExerciseIndex = -1;
        let masterClockInterval;

    var nummyCookie = document.cookie.split('; ').find(row => row.startsWith('nummy_cookie='));
        //console.log(`Cookie created: ${nummyCookie}`);
    var filename = nummyCookie ? nummyCookie.split('=')[1] : 'default_filename';
        //console.log(`Filename created: ${filename}`);
    var xmlFilePath = '/1.0/workouts/' + filename + '_workout.xml';  // Include the '1.0' in the path
        //console.log(`Filepath decoded: ${xmlFilePath}`);



       function loadXML() {
    fetch(xmlFilePath)
        .then(response => {
            if (!response.ok) {
                throw new Error('Network response was not ok');
            }
            return response.text();
        })
        .then(data => {
            let parser = new DOMParser();
            let xmlDoc = parser.parseFromString(data, "text/xml");
            exerciseData = xmlDoc.getElementsByTagName("Exercise");
            console.log("Exercise Data loaded: ", exerciseData);
        })
        .catch(error => {
            console.error('There has been a problem with your fetch operation:', error);
        });
}



    // Checks and displays current movements
        function updateExercise() {
    //console.log(`updateExercise called`);
    let currentTime = Date.now();
    //console.log(`Current Time (ms): ${currentTime}`);

    if (!exerciseData || exerciseData.length === 0) {
        console.error(`Exercise data is not defined or empty`);
        return;
    }
            let currentExercise = document.getElementById("exercise");
            let currentExerciseName = document.getElementById("exercise-name");
            let currentExerciseDescription = document.getElementById("exercise-description");
            let currentExerciseSets = document.getElementById("exercise-sets");
            let currentExerciseReps = document.getElementById("exercise-reps");
            let currentExerciseRest = document.getElementById("exercise-rest");

            let elapsedTime = currentTime - masterClockInterval.startTime;
            currentTime = elapsedTime;
            //console.log("Current Time (ms): " + currentTime);  // Debugging output

            let exerciseFound = false;

            for (let i = 0; i < exerciseData.length; i++) {
                let exercise = exerciseData[i];
                let timestampStart = parseInt(exercise.getElementsByTagName("TimestampStart")[0].textContent);
                let timestampEnd = parseInt(exercise.getElementsByTagName("TimestampEnd")[0].textContent);

                //console.log("Exercise " + (i+1));  // Debugging output
                //console.log("Timestamp Start (ms): " + timestampStart);  // Debugging output
                //console.log("Timestamp End (ms): " + timestampEnd);  // Debugging output

                if (currentTime >= timestampStart && currentTime <= timestampEnd) {
                    currentExercise.style.display = "block";
                    currentExerciseName.innerText = exercise.getElementsByTagName("Name")[0].textContent;
                    currentExerciseDescription.innerText = exercise.getElementsByTagName("Description")[0].textContent;
                    currentExerciseSets.innerText = exercise.getElementsByTagName("Sets")[0].textContent;
                    currentExerciseReps.innerText = exercise.getElementsByTagName("Reps")[0].textContent;
                    currentExerciseRest.innerText = exercise.getElementsByTagName("Rest")[0].textContent;
                    exerciseFound = true;
                    break;
                }
            }

            if (!exerciseFound) {
                currentExercise.style.display = "none";
                clearInterval(masterClockInterval);
            }
        }

    // Tickrate of master clock

        function updateMasterClock() {
            let masterClock = document.getElementById("master-clock");
            let currentTime = Date.now();
            let elapsedTime = currentTime - masterClockInterval.startTime;
            let minutes = Math.floor(elapsedTime / 60000);
            let seconds = Math.floor((elapsedTime % 60000) / 1000);
            let milliseconds = elapsedTime % 1000;
            masterClock.textContent = `${String(minutes).padStart(2, "0")}:${String(seconds).padStart(2, "0")}.${String(milliseconds).padStart(3, "0")}`;
        }

    // Toggles Play/Pause Button

    function toggleMasterClock() {
        //console.log("toggleMasterClock called");
    if (masterClockInterval && masterClockInterval.interval) {
        // If the clock is running, pause it
        clearInterval(masterClockInterval.interval);
        let currentTime = Date.now();
        let elapsedTime = currentTime - masterClockInterval.startTime;
        masterClockInterval.elapsedTime = elapsedTime;
        delete masterClockInterval.interval;
        document.getElementById('toggle-button').innerText = 'Start';
    } else {
        // If the clock is paused or hasn't been started yet, start it
        if (masterClockInterval && masterClockInterval.elapsedTime) {
            masterClockInterval.startTime = Date.now() - masterClockInterval.elapsedTime;
        } else {
            masterClockInterval.startTime = Date.now();
        }
        updateExercise();
        updateMasterClock();
        masterClockInterval.interval = setInterval(function () {
            updateExercise();
            updateMasterClock();
        }, 10);
        document.getElementById('toggle-button').innerText = 'Pause';
    }
}

document.addEventListener("DOMContentLoaded", () => {
  let setStart = 1;
  let repStart = 1;
  let selectedSet = null;
  let selectedRep = null;

  function generateNumbers(start, end, containerId, selected) {
    const container = document.getElementById(containerId);
    container.innerHTML = "";
    for (let i = start; i <= end; i++) {
      const numElement = document.createElement("div");
      numElement.className = "number";
      numElement.textContent = i;
      if (i === selected) {
        numElement.classList.add("selected");
      }
      numElement.addEventListener("click", () => handleNumberClick(i, containerId));
      container.appendChild(numElement);
    }
  }

  function handleNumberClick(selectedNumber, containerId) {
    const midpoint = Math.floor((setStart + 6 - 1) / 2);
    if (containerId === "setNumbers") {
      selectedSet = selectedNumber;
      if (selectedNumber > midpoint) {
        setStart++;
      }
      generateNumbers(setStart, setStart + 5, containerId, selectedSet);
    } else {
      selectedRep = selectedNumber;
      if (selectedNumber > midpoint) {
        repStart++;
      }
      generateNumbers(repStart, repStart + 5, containerId, selectedRep);
    }
  }

  generateNumbers(setStart, setStart + 5, "setNumbers", selectedSet);
  generateNumbers(repStart, repStart + 5, "repNumbers", selectedRep);
});

    </script>
</head>
<body onload="masterClockInterval = { startTime: Date.now() }; loadXML(getParameterByName('xmlFile'));">
<br><br>
<div class="master">
    <div class="box">
    <button class="button is-danger" id="toggle-button" onclick="toggleMasterClock()">Start</button>

    <div>
        <p id="master-clock">00:00:00.000</p>
    </div>
    </div>
      <div class="box" id="exercise" style="display: none;">
        <h2 id="exercise-name"></h2>
        <p id="exercise-description"></p>
        <p>Sets: <span id="exercise-sets"></span></p>
        <p>Reps: <span id="exercise-reps"></span></p>
        <p>Rest: <span id="exercise-rest"></span></p>
      </div>
    <br>
    <br>
      <!-- <div class="box">
        <div class="container">
            <div id="setContainer" class="tracker">
                <p>Sets</p>
                    <div id="setNumbers" class="numbers">
                        <!-- Numbers will be generated by JS --
                    </div>
            </div>
            <div id="repContainer" class="tracker">
                <p>Reps</p>
                    <div id="repNumbers" class="numbers">
                    <!-- Numbers will be generated by JS --
                </div>
            </div>
        </div>
      </div> -->
    <br>
    <br>
    <br>
    <br>
    <a href="https://mov.fit/1.0">Create your next Workout</a>
    </div>
</body>
</html>