(function memoryGameModule() {
  const STAGE_CONFIG = {
    1: { displayTime: 10, guessTime: 120, levels: 10 },
    2: { displayTime: 8, guessTime: 90, levels: 10 },
    3: { displayTime: 3, guessTime: 60, levels: 10 }
  };

  const state = {
    currentStage: 1,
    currentLevel: 1,
    currentPlate: null,
    typedAnswer: "",
    displayTimer: null,
    guessTimer: null,
    displayTimeLeft: 0,
    guessTimeLeft: 0,
    isDisplaying: false,
    isGuessing: false,
    isCompleted: false,
    isActiveSection: false,
    totalLevels: 30,
    completedLevels: 0
  };

  let refs = null;

  function byId(id) {
    return document.getElementById(id);
  }

  function getRefs() {
    return {
      gameCard: byId("memory-game-card"),
      stageDisplay: byId("mg-stage-display"),
      levelDisplay: byId("mg-level-display"),
      plateDisplay: byId("mg-plate-display"),
      inputContainer: byId("mg-input-container"), // Updated element reference
      userInputField: byId("mg-user-input"),      // Updated element reference
      displayTimer: byId("mg-display-timer"),
      guessTimer: byId("mg-guess-timer"),
      progressFill: byId("mg-progress-fill"),
      progressText: byId("mg-progress-text"),
      feedback: byId("mg-feedback"),
      completeBtn: byId("mg-complete-btn"),
      startBtn: byId("mg-start-btn"),
      nextLevelBtn: byId("mg-next-level-btn")
    };
  }

  function generatePlateNumber() {
    const letters = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';
    const numbers = '0123456789';
    
    let plate = '';
    for (let i = 0; i < 3; i++) {
      plate += letters.charAt(Math.floor(Math.random() * letters.length));
    }
    plate += '-';
    for (let i = 0; i < 4; i++) {
      plate += numbers.charAt(Math.floor(Math.random() * numbers.length));
    }
    return plate;
  }

  function formatTimer(seconds) {
    const mins = String(Math.floor(seconds / 60)).padStart(2, "0");
    const secs = String(seconds % 60).padStart(2, "0");
    return `${mins}:${secs}`;
  }

  function setFeedback(text, type) {
    refs.feedback.textContent = text;
    refs.feedback.className = "mg-feedback";
    if (type) refs.feedback.classList.add(type);
  }

  function updateProgressUI() {
    const percent = Math.round((state.completedLevels / state.totalLevels) * 100);
    refs.progressFill.style.width = `${percent}%`;
    refs.progressText.textContent = `${percent}%`;
  }

  function clearTimers() {
    if (state.displayTimer) {
      clearInterval(state.displayTimer);
      state.displayTimer = null;
    }
    if (state.guessTimer) {
      clearInterval(state.guessTimer);
      state.guessTimer = null;
    }
  }

  function startDisplayPhase() {
    state.isDisplaying = true;
    state.isGuessing = false;
    state.displayTimeLeft = STAGE_CONFIG[state.currentStage].displayTime;
    
    const plateNumberEl = refs.plateDisplay.querySelector('.plate-number');
    if (plateNumberEl) {
      plateNumberEl.textContent = state.currentPlate;
    } else {
      refs.plateDisplay.textContent = state.currentPlate;
    }
    
    // UI Layout Toggling
    refs.plateDisplay.style.display = 'grid';
    refs.inputContainer.style.display = 'none';
    refs.userInputField.value = ""; // Clear old answers
    refs.userInputField.disabled = true;
    
    refs.completeBtn.disabled = true;
    refs.startBtn.disabled = true;
    refs.nextLevelBtn.style.display = 'none';
    
    setFeedback(`Memorize this plate number! ${state.displayTimeLeft} seconds remaining...`, "info");
    
    state.displayTimer = setInterval(() => {
      state.displayTimeLeft--;
      refs.displayTimer.textContent = formatTimer(state.displayTimeLeft);
      
      if (state.displayTimeLeft <= 0) {
        clearTimers();
        startGuessPhase();
      }
    }, 1000);
  }

  function startGuessPhase() {
    state.isDisplaying = false;
    state.isGuessing = true;
    state.guessTimeLeft = STAGE_CONFIG[state.currentStage].guessTime;
    
    // UI Layout Toggling
    refs.plateDisplay.style.display = 'none';
    refs.inputContainer.style.display = 'block';
    refs.userInputField.disabled = false;
    refs.userInputField.focus(); // Auto-focus the box so they can just type
    
    refs.completeBtn.disabled = false;
    
    setFeedback(`Type the plate number you just saw! ${state.guessTimeLeft} seconds remaining...`, "info");
    
    state.guessTimer = setInterval(() => {
      state.guessTimeLeft--;
      refs.guessTimer.textContent = formatTimer(state.guessTimeLeft);
      
      if (state.guessTimeLeft <= 0) {
        clearTimers();
        endByTimeout();
      }
    }, 1000);
  }

  function checkAnswer() {
    // Process input data cleanly (strip spacing gaps and match casing)
    const rawInput = refs.userInputField.value.trim().toUpperCase();
    
    if (!rawInput) {
      setFeedback("Please type your answer first!", "error");
      return;
    }
    
    clearTimers();
    refs.userInputField.disabled = true;
    
    if (rawInput === state.currentPlate) {
      setFeedback("Correct! Excellent memory verification!", "success");
      state.completedLevels++;
      updateProgressUI();
      refs.nextLevelBtn.style.display = 'block';
      refs.completeBtn.disabled = true;
      
      saveMemoryProgress();
      
      if (state.currentLevel === 10 && state.currentStage === 3) {
        completeGame();
      }
    } else {
      setFeedback(`Wrong! You entered "${rawInput}". The correct plate was ${state.currentPlate}.`, "error");
      refs.startBtn.disabled = false;
      refs.completeBtn.disabled = true;
    }
  }

  function nextLevel() {
    if (state.currentLevel < 10) {
      state.currentLevel++;
    } else if (state.currentStage < 3) {
      state.currentStage++;
      state.currentLevel = 1;
    } else {
      completeGame();
      return;
    }
    
    updateLevelDisplay();
    startNewLevel();
  }

  function startNewLevel() {
    state.currentPlate = generatePlateNumber();
    state.isCompleted = false;
    
    refs.displayTimer.textContent = formatTimer(STAGE_CONFIG[state.currentStage].displayTime);
    refs.guessTimer.textContent = formatTimer(STAGE_CONFIG[state.currentStage].guessTime);
    
    startDisplayPhase();
  }

  function updateLevelDisplay() {
    refs.stageDisplay.textContent = `Stage ${state.currentStage}`;
    refs.levelDisplay.textContent = `Level ${state.currentLevel}`;
  }

  function completeGame() {
    state.isCompleted = true;
    clearTimers();
    refs.gameCard.classList.add("is-disabled");
    refs.userInputField.disabled = true;
    refs.completeBtn.disabled = true;
    refs.startBtn.disabled = true;
    refs.nextLevelBtn.style.display = 'none';
    setFeedback("Congratulations! You've mastered all stages of the Memory Game!", "success");
    saveMemoryProgress();
  }

  function endByTimeout() {
    refs.gameCard.classList.add("is-disabled");
    refs.userInputField.disabled = true;
    refs.completeBtn.disabled = true;
    refs.startBtn.disabled = false;
    setFeedback(`Time's up! The correct plate was ${state.currentPlate}. Click Start to try again.`, "error");
  }

  function saveMemoryProgress() {
    const progressPercent = Math.round((state.completedLevels / state.totalLevels) * 100);
    const saveUrl = typeof ROADRANGER_SAVE_URL !== 'undefined' ? ROADRANGER_SAVE_URL : "memory_progress.php";

    fetch(saveUrl, {
      method: "POST",
      headers: { "Content-Type": "application/x-www-form-urlencoded" },
      body: new URLSearchParams({
        game_name: "memory_game",
        stage_number: state.currentStage,
        is_completed: state.isCompleted ? 1 : 0,
        total_stages: 3,
        completed_levels: state.completedLevels,
        progress_percent: String(progressPercent)
      })
    })
    .then(async (res) => {
      const text = await res.text();
      let data = null;
      try {
        data = JSON.parse(text);
      } catch (error) {
        throw new Error(`Unexpected server output (${res.status}): ${text.slice(0, 150)}`);
      }
      if (!res.ok || !data || !data.success) {
        throw new Error(data?.message || `Save failed (${res.status})`);
      }
      return data;
    })
    .then((data) => {
      if (data.game_completed) {
        setFeedback("Great work! All progress saved to your account.", "success");
      }
    })
    .catch((error) => {
      setFeedback(`Progress save failed: ${error.message}`, "error");
    });
  }

  function activateSection(isActive) {
    state.isActiveSection = isActive;
    if (state.isCompleted) return;
    if (isActive) {
      refs.gameCard.classList.remove("is-disabled");
      refs.startBtn.disabled = false;
    } else {
      clearTimers();
      refs.gameCard.classList.add("is-disabled");
    }
  }

  function init() {
    refs = getRefs();
    if (!refs.gameCard) return;

    refs.displayTimer.textContent = formatTimer(STAGE_CONFIG[1].displayTime);
    refs.guessTimer.textContent = formatTimer(STAGE_CONFIG[1].guessTime);
    
    refs.startBtn.addEventListener("click", startNewLevel);
    refs.completeBtn.addEventListener("click", checkAnswer);
    refs.nextLevelBtn.addEventListener("click", nextLevel);

    refs.userInputField.addEventListener("keydown", (e) => {
      if (e.key === "Enter" && !refs.completeBtn.disabled) {
        checkAnswer();
      }
    });
    
    updateLevelDisplay();
    updateProgressUI();
    setFeedback("Click Start to begin the Memory Game!", "info");

    const initialActive = document.getElementById("section-memory")?.classList.contains("active");
    activateSection(Boolean(initialActive));

    window.addEventListener("learnTab:sectionChanged", (event) => {
      const activeSection = event?.detail?.sectionId === "memory";
      activateSection(activeSection);
    });
  }

  document.addEventListener("DOMContentLoaded", init);
})();