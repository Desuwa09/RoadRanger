(function memoryGameModule() {
  const STAGE_CONFIG = {
    1: { displayTime: 10, guessTime: 120, levels: 10 },
    2: { displayTime: 8, guessTime: 90, levels: 10 },
    3: { displayTime: 3, guessTime: 60, levels: 10 }
  };

  const LETTER_POOL = ['A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'I', 'J', 'K', 'L', 'M', 'N', 'O', 'P', 'Q', 'R', 'S', 'T', 'U', 'V', 'W', 'X', 'Y', 'Z'];
  const NUMBER_POOL = ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9'];

  const DIFFICULTY_MODE_MAP = {
    easy: { stage: 1, label: 'Easy', builder: false },
    medium: { stage: 2, label: 'Medium', builder: true },
    hard: { stage: 3, label: 'Hard', builder: true }
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
    totalLevels: 10,
    completedLevels: 0,
    activeBuilderSlot: null,
    builderChoices: {
      letters: [],
      numbers: []
    },
    builderSelections: {
      letters: "",
      numbers: ""
    }
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
      inputContainer: byId("mg-input-container"),
      userInputField: byId("mg-user-input"),
      displayTimer: byId("mg-display-timer"),
      guessTimer: byId("mg-guess-timer"),
      progressFill: byId("mg-progress-fill"),
      progressText: byId("mg-progress-text"),
      feedback: byId("mg-feedback"),
      completeBtn: byId("mg-complete-btn"),
      startBtn: byId("mg-start-btn"),
      nextLevelBtn: byId("mg-next-level-btn"),
      difficultySelect: byId("mg-difficulty-select"),
      mediumBuilder: byId("mg-medium-builder"),
      builderSlotLabel: byId("mg-builder-slot-label"),
      letterSlots: byId("mg-letter-slots"),
      numberSlots: byId("mg-number-slots"),
      letterOptions: byId("mg-letter-options"),
      numberOptions: byId("mg-number-options")
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

  function getSelectedDifficulty() {
    const selected = refs.difficultySelect?.value || "";
    return DIFFICULTY_MODE_MAP[selected] || null;
  }

  function applySelectedDifficulty() {
    const mode = getSelectedDifficulty();
    if (!mode) {
      state.currentStage = 1;
      state.currentLevel = 1;
      state.totalLevels = STAGE_CONFIG[1].levels;
      state.completedLevels = 0;
      updateLevelDisplay();
      updateProgressUI();
      refs.startBtn.disabled = true;
      setFeedback("Choose a mode to begin the Memory Game.", "info");
      return;
    }

    state.currentStage = mode.stage;
    state.currentLevel = 1;
    state.totalLevels = STAGE_CONFIG[mode.stage].levels;
    state.completedLevels = 0;
    updateLevelDisplay();
    updateProgressUI();
    refs.startBtn.disabled = false;
    setFeedback(`Mode selected: ${mode.label}. Click Start to begin.`, "info");
  }

  function shuffleArray(items) {
    const copy = [...items];
    for (let i = copy.length - 1; i > 0; i--) {
      const j = Math.floor(Math.random() * (i + 1));
      [copy[i], copy[j]] = [copy[j], copy[i]];
    }
    return copy;
  }

  function buildChoicePool(answer, poolSize, sourceCharacters, length) {
    const pool = [answer];
    while (pool.length < poolSize) {
      let candidate = "";
      for (let i = 0; i < length; i++) {
        candidate += sourceCharacters[Math.floor(Math.random() * sourceCharacters.length)];
      }
      if (!pool.includes(candidate)) {
        pool.push(candidate);
      }
    }
    return shuffleArray(pool);
  }

  function generateBuilderChoices(plate) {
    const [plateLetters, plateNumbers] = plate.split("-");
    return {
      letters: buildChoicePool(plateLetters, 6, LETTER_POOL, 3),
      numbers: buildChoicePool(plateNumbers, 6, NUMBER_POOL, 4)
    };
  }

  function renderBuilderSlots() {
    refs.letterSlots.innerHTML = "";
    refs.numberSlots.innerHTML = "";

    const letterSlotBtn = document.createElement("button");
    letterSlotBtn.type = "button";
    letterSlotBtn.textContent = state.builderSelections.letters || "Choose Word";
    letterSlotBtn.style.padding = "10px 14px";
    letterSlotBtn.style.minWidth = "120px";
    letterSlotBtn.style.borderRadius = "6px";
    letterSlotBtn.style.border = "1px solid #cbd5e1";
    letterSlotBtn.style.background = state.builderSelections.letters ? "#dbeafe" : "#fff";
    letterSlotBtn.style.cursor = "pointer";
    letterSlotBtn.addEventListener("click", () => {
      state.activeBuilderSlot = "letters";
      refs.builderSlotLabel.textContent = "Active selection: Letter group";
    });
    refs.letterSlots.appendChild(letterSlotBtn);

    const numberSlotBtn = document.createElement("button");
    numberSlotBtn.type = "button";
    numberSlotBtn.textContent = state.builderSelections.numbers || "Choose Number";
    numberSlotBtn.style.padding = "10px 14px";
    numberSlotBtn.style.minWidth = "120px";
    numberSlotBtn.style.borderRadius = "6px";
    numberSlotBtn.style.border = "1px solid #cbd5e1";
    numberSlotBtn.style.background = state.builderSelections.numbers ? "#dcfce7" : "#fff";
    numberSlotBtn.style.cursor = "pointer";
    numberSlotBtn.addEventListener("click", () => {
      state.activeBuilderSlot = "numbers";
      refs.builderSlotLabel.textContent = "Active selection: Number group";
    });
    refs.numberSlots.appendChild(numberSlotBtn);

    refs.letterOptions.innerHTML = "";
    state.builderChoices.letters.forEach((letterGroup) => {
      const btn = document.createElement("button");
      btn.type = "button";
      btn.textContent = letterGroup;
      btn.style.padding = "8px 10px";
      btn.style.borderRadius = "6px";
      btn.style.border = "1px solid #bfdbfe";
      btn.style.background = "#eff6ff";
      btn.style.cursor = "pointer";
      btn.addEventListener("click", () => {
        state.activeBuilderSlot = "letters";
        state.builderSelections.letters = letterGroup;
        refs.builderSlotLabel.textContent = `Selected word: ${letterGroup}`;
        renderBuilderSlots();
      });
      refs.letterOptions.appendChild(btn);
    });

    refs.numberOptions.innerHTML = "";
    state.builderChoices.numbers.forEach((numberGroup) => {
      const btn = document.createElement("button");
      btn.type = "button";
      btn.textContent = numberGroup;
      btn.style.padding = "8px 10px";
      btn.style.borderRadius = "6px";
      btn.style.border = "1px solid #bbf7d0";
      btn.style.background = "#f0fdf4";
      btn.style.cursor = "pointer";
      btn.addEventListener("click", () => {
        state.activeBuilderSlot = "numbers";
        state.builderSelections.numbers = numberGroup;
        refs.builderSlotLabel.textContent = `Selected digits: ${numberGroup}`;
        renderBuilderSlots();
      });
      refs.numberOptions.appendChild(btn);
    });
  }

  function resetBuilderSelections() {
    state.activeBuilderSlot = "letters";
    state.builderSelections.letters = "";
    state.builderSelections.numbers = "";
    state.builderChoices = generateBuilderChoices(state.currentPlate);
    renderBuilderSlots();
  }

  function updateProgressUI() {
    const percent = Math.round((state.completedLevels / Math.max(state.totalLevels, 1)) * 100);
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
    
    refs.plateDisplay.style.display = 'grid';
    refs.inputContainer.style.display = 'none';
    refs.userInputField.value = "";
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
    
    refs.plateDisplay.style.display = 'none';
    const mode = getSelectedDifficulty();
    const usesBuilder = Boolean(mode && mode.builder);

    refs.inputContainer.style.display = usesBuilder ? 'none' : 'block';
    refs.mediumBuilder.style.display = usesBuilder ? 'block' : 'none';

    if (usesBuilder) {
      resetBuilderSelections();
      refs.completeBtn.disabled = false;
      setFeedback(`Build the plate number from the letter and number choices! ${state.guessTimeLeft} seconds remaining...`, "info");
    } else {
      refs.userInputField.disabled = false;
      refs.userInputField.focus();
      refs.completeBtn.disabled = false;
      setFeedback(`Type the plate number you just saw! ${state.guessTimeLeft} seconds remaining...`, "info");
    }
    
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
    const mode = getSelectedDifficulty();
    const usesBuilder = Boolean(mode && mode.builder);
    let submittedAnswer = "";

    if (usesBuilder) {
      const letterAnswer = state.builderSelections.letters.trim().toUpperCase();
      const numberAnswer = state.builderSelections.numbers.trim().toUpperCase();
      submittedAnswer = `${letterAnswer}-${numberAnswer}`;

      if (!letterAnswer || !numberAnswer || letterAnswer.length < 3 || numberAnswer.length < 4) {
        setFeedback("Please choose the correct letter group and number group before submitting.", "error");
        return;
      }
    } else {
      submittedAnswer = refs.userInputField.value.trim().toUpperCase();
      if (!submittedAnswer) {
        setFeedback("Please type your answer first!", "error");
        return;
      }
    }
    
    clearTimers();
    refs.userInputField.disabled = true;
    
    if (submittedAnswer === state.currentPlate) {
      setFeedback("Correct! Excellent memory verification!", "success");
      state.completedLevels++;
      updateProgressUI();
      refs.nextLevelBtn.style.display = 'block';
      refs.completeBtn.disabled = true;
      
      saveMemoryProgress();
      
      const selectedMode = getSelectedDifficulty();
      const levelCap = selectedMode ? STAGE_CONFIG[selectedMode.stage].levels : state.totalLevels;
      if (state.currentLevel >= levelCap) {
        completeGame();
      }
    } else {
      setFeedback(`Wrong! You entered "${submittedAnswer}". The correct plate was ${state.currentPlate}.`, "error");
      refs.startBtn.disabled = false;
      refs.completeBtn.disabled = true;
    }
  }

  function nextLevel() {
    const selectedMode = getSelectedDifficulty();
    const levelCap = selectedMode ? STAGE_CONFIG[selectedMode.stage].levels : state.totalLevels;

    if (state.currentLevel < levelCap) {
      state.currentLevel++;
    } else {
      completeGame();
      return;
    }
    
    updateLevelDisplay();
    startNewLevel();
  }

  function startNewLevel() {
    const selectedMode = getSelectedDifficulty();
    if (!selectedMode) {
      setFeedback("Please choose a difficulty mode first.", "error");
      return;
    }

    state.currentStage = selectedMode.stage;
    state.totalLevels = STAGE_CONFIG[state.currentStage].levels;
    state.currentPlate = generatePlateNumber();
    state.builderChoices = generateBuilderChoices(state.currentPlate);
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
    setFeedback("Congratulations! You've completed the selected Memory Game mode!", "success");
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
    renderBuilderSlots();
    
    refs.startBtn.addEventListener("click", startNewLevel);
    refs.completeBtn.addEventListener("click", checkAnswer);
    refs.nextLevelBtn.addEventListener("click", nextLevel);
    refs.difficultySelect.addEventListener("change", () => {
      applySelectedDifficulty();
    });

    refs.userInputField.addEventListener("keydown", (e) => {
      if (e.key === "Enter" && !refs.completeBtn.disabled) {
        checkAnswer();
      }
    });
    
    updateLevelDisplay();
    updateProgressUI();
    applySelectedDifficulty();

    const initialActive = document.getElementById("section-memory")?.classList.contains("active");
    activateSection(Boolean(initialActive));

    window.addEventListener("learnTab:sectionChanged", (event) => {
      const activeSection = event?.detail?.sectionId === "memory";
      activateSection(activeSection);
    });
  }

  document.addEventListener("DOMContentLoaded", init);
})();