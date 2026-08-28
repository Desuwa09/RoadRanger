(function conveyorManiaModule() {
  const GAME_DURATION_SECONDS = 120;
  const signs = [
    { id: 1, src: "../../assets/imgs/Signs/stop.png", category: "regulatory", label: "Stop" },
    { id: 2, src: "../../assets/imgs/Signs/giveway.jpg", category: "regulatory", label: "Give Way" },
    { id: 3, src: "../../assets/imgs/Signs/no_entry.png", category: "regulatory", label: "No Entry" },
    { id: 4, src: "../../assets/imgs/Signs/no_leftturn.png", category: "regulatory", label: "No Left Turn" },
    { id: 5, src: "../../assets/imgs/Signs/no_rightturn.png", category: "regulatory", label: "No Right Turn" },
    { id: 6, src: "../../assets/imgs/Signs/max_60.png", category: "regulatory", label: "Maximum 60" },
    { id: 7, src: "../../assets/imgs/Signs/min_40.png", category: "regulatory", label: "Minimum 40" },
    { id: 8, src: "../../assets/imgs/Signs/slow_down.png", category: "warning", label: "Slow Down" },
    { id: 9, src: "../../assets/imgs/Signs/use_overpass.png", category: "warning", label: "Use Overpass" },
    { id: 10, src: "../../assets/imgs/Signs/keep_right.png", category: "warning", label: "Keep Right" },
    { id: 11, src: "../../assets/imgs/Signs/one_way.png", category: "informative", label: "One Way" },
    { id: 12, src: "../../assets/imgs/Signs/bike_lane.png", category: "informative", label: "Bike Lane" }
  ];

  const state = {
    queue: [],
    currentSign: null,
    sortedCount: 0,
    timerLeft: GAME_DURATION_SECONDS,
    timerId: null,
    isActiveSection: false,
    isCompleted: false,
    pointerDrag: {
      active: false
    }
  };

  let refs = null;

  function byId(id) {
    return document.getElementById(id);
  }

  function getRefs() {
    return {
      gameCard: byId("conveyor-mania-game"),
      sign: byId("cm-active-sign"),
      skipBtn: byId("cm-skip-btn"),
      timeLeft: byId("cm-time-left"),
      sortedCount: byId("cm-sorted-count"),
      totalCount: byId("cm-total-count"),
      progressFill: byId("cm-progress-fill"),
      progressText: byId("cm-progress-text"),
      feedback: byId("cm-feedback"),
      currentSignLabel: byId("cm-current-sign-label"),
      dropzones: Array.from(document.querySelectorAll(".cm-dropzone"))
    };
  }

  function shuffle(arr) {
    const cloned = [...arr];
    for (let i = cloned.length - 1; i > 0; i -= 1) {
      const j = Math.floor(Math.random() * (i + 1));
      const temp = cloned[i];
      cloned[i] = cloned[j];
      cloned[j] = temp;
    }
    return cloned;
  }

  function formatTimer(seconds) {
    const mins = String(Math.floor(seconds / 60)).padStart(2, "0");
    const secs = String(seconds % 60).padStart(2, "0");
    return `${mins}:${secs}`;
  }

  function setFeedback(text, type) {
    refs.feedback.textContent = text;
    refs.feedback.className = "cm-feedback";
    if (type) refs.feedback.classList.add(type);
  }

  function updateProgressUI() {
    const percent = Math.round((state.sortedCount / signs.length) * 100);
    refs.sortedCount.textContent = String(state.sortedCount);
    refs.progressFill.style.width = `${percent}%`;
    refs.progressText.textContent = `${percent}%`;
  }

  function clearTimer() {
    if (state.timerId) {
      window.clearInterval(state.timerId);
      state.timerId = null;
    }
  }

  function startTimer() {
    if (state.timerId || state.isCompleted || !state.isActiveSection) return;
    state.timerId = window.setInterval(() => {
      state.timerLeft -= 1;
      refs.timeLeft.textContent = formatTimer(Math.max(state.timerLeft, 0));
      if (state.timerLeft <= 0) {
        clearTimer();
        endByTimeout();
      }
    }, 1000);
  }

  function markDropzoneFlash(zone, className) {
    zone.classList.add(className);
    window.setTimeout(() => zone.classList.remove(className), 260);
  }

  function renderCurrentSign() {
    state.currentSign = state.queue[0] || null;

    if (!state.currentSign) {
      refs.sign.style.display = "none";
      refs.currentSignLabel.textContent = "Current Sign: --";
      return;
    }

    refs.sign.src = state.currentSign.src;
    refs.sign.alt = `${state.currentSign.label} road sign`;
    refs.sign.style.display = "block";
    refs.currentSignLabel.textContent = `Current Sign: ${state.currentSign.label}`;

    refs.sign.classList.remove("is-dragging");
    refs.sign.style.left = "";
    refs.sign.style.top = "";
    refs.sign.style.position = "";
    refs.sign.style.transform = "";
    refs.sign.style.pointerEvents = "";
    refs.sign.style.animation = "none";
    void refs.sign.offsetWidth;
    refs.sign.style.animation = "";
  }

  function skipCurrentSign() {
    if (!state.currentSign || state.isCompleted) return;
    const skipped = state.queue.shift();
    state.queue.push(skipped);
    setFeedback("Sign skipped. It will return later in the loop.", "info");
    renderCurrentSign();
  }

  function completeGame() {
    state.isCompleted = true;
    clearTimer();
    refs.gameCard.classList.add("is-disabled");
    refs.sign.draggable = false;
    refs.skipBtn.disabled = true;
    setFeedback("Great work! Conveyor Mania complete.", "success");
    saveConveyorProgress();
  }

  function endByTimeout() {
    refs.gameCard.classList.add("is-disabled");
    refs.sign.draggable = false;
    refs.skipBtn.disabled = true;
    setFeedback("Time's up. Reopen Conveyor Mania to try again.", "error");
  }

  function saveConveyorProgress() {
    const progressPercent = Math.round((state.sortedCount / signs.length) * 100);
    fetch("conveyor_progress.php", {
      method: "POST",
      headers: { "Content-Type": "application/x-www-form-urlencoded" },
      body: new URLSearchParams({
        game_name: "conveyor_mania",
        stage_number: "1",
        is_completed: "1",
        total_stages: "1",
        progress_percent: String(progressPercent)
      })
    })
      .then(async (res) => {
        const text = await res.text();
        let data = null;
        try {
          data = JSON.parse(text);
        } catch (error) {
          throw new Error(`Unexpected response (${res.status}): ${text.slice(0, 180)}`);
        }
        if (!res.ok || !data || !data.success) {
          throw new Error(data?.message || `Save failed (${res.status})`);
        }
        return data;
      })
      .then((data) => {
        if (data.game_completed) setFeedback("Great work! Progress saved to your account.", "success");
      })
      .catch((error) => {
        setFeedback(`Completed locally, but progress save failed: ${error.message}`, "error");
      });
  }

  function handleDrop(category, zone) {
    if (!state.currentSign || state.isCompleted || !state.isActiveSection) return;

    if (state.currentSign.category === category) {
      markDropzoneFlash(zone, "correct-flash");
      state.queue.shift();
      state.sortedCount += 1;
      updateProgressUI();
      setFeedback(`Correct: ${state.currentSign.label} is ${category}.`, "success");

      if (state.sortedCount >= signs.length) {
        renderCurrentSign();
        completeGame();
        return;
      }

      renderCurrentSign();
      return;
    }

    markDropzoneFlash(zone, "wrong-flash");
    setFeedback(`Incorrect. ${state.currentSign.label} does not belong in ${category}.`, "error");
    renderCurrentSign();
  }

  function beginPointerDrag(event) {
    if (!state.currentSign || state.isCompleted || !state.isActiveSection) return;
    if (event.button !== undefined && event.button !== 0) return;

    state.pointerDrag.active = true;
    refs.sign.classList.add("is-dragging");
    refs.sign.style.animation = "none";
    refs.sign.style.position = "fixed";
    refs.sign.style.transform = "translate(-50%, -50%)";

    updatePointerSignPosition(event.clientX, event.clientY);
  }

  function updatePointerSignPosition(clientX, clientY) {
    if (!state.pointerDrag.active) return;
    refs.sign.style.left = `${clientX}px`;
    refs.sign.style.top = `${clientY}px`;
  }

  function endPointerDrag(event) {
    if (!state.pointerDrag.active) return;
    state.pointerDrag.active = false;
    refs.sign.classList.remove("is-dragging");

    const target = document.elementFromPoint(event.clientX, event.clientY);
    const zone = target ? target.closest(".cm-dropzone") : null;

    if (zone) {
      handleDrop(zone.dataset.category, zone);
    } else {
      renderCurrentSign();
    }
  }

  function bindDragDrop() {
    refs.sign.addEventListener("dragstart", (event) => {
      if (!state.currentSign || state.isCompleted || !state.isActiveSection) {
        event.preventDefault();
        return;
      }
      refs.sign.classList.add("is-dragging");
      event.dataTransfer.setData("text/plain", String(state.currentSign.id));
      event.dataTransfer.effectAllowed = "move";
    });

    refs.sign.addEventListener("dragend", () => {
      refs.sign.classList.remove("is-dragging");
    });

    refs.dropzones.forEach((zone) => {
      zone.addEventListener("dragenter", (event) => {
        event.preventDefault();
        zone.classList.add("is-over");
      });

      zone.addEventListener("dragover", (event) => {
        event.preventDefault();
        zone.classList.add("is-over");
      });

      zone.addEventListener("dragleave", () => {
        zone.classList.remove("is-over");
      });

      zone.addEventListener("drop", (event) => {
        event.preventDefault();
        zone.classList.remove("is-over");
        const droppedId = Number(event.dataTransfer.getData("text/plain"));
        if (!state.currentSign || droppedId !== state.currentSign.id) return;
        handleDrop(zone.dataset.category, zone);
      });
    });

    refs.sign.addEventListener("pointerdown", (event) => {
      beginPointerDrag(event);
    });

    window.addEventListener("pointermove", (event) => {
      updatePointerSignPosition(event.clientX, event.clientY);
    });

    window.addEventListener("pointerup", (event) => {
      endPointerDrag(event);
    });
  }

  function activateSection(isActive) {
    state.isActiveSection = isActive;
    if (state.isCompleted) return;
    if (isActive) {
      refs.gameCard.classList.remove("is-disabled");
      refs.sign.draggable = true;
      refs.skipBtn.disabled = false;
      startTimer();
    } else {
      clearTimer();
    }
  }

  function init() {
    refs = getRefs();
    if (!refs.gameCard || !refs.sign) return;

    state.isCompleted = refs.gameCard.dataset.conveyorLocked === "1";

    refs.totalCount.textContent = String(signs.length);
    refs.timeLeft.textContent = formatTimer(state.timerLeft);
    refs.skipBtn.addEventListener("click", skipCurrentSign);

    bindDragDrop();

    state.queue = shuffle(signs);
    updateProgressUI();
    setFeedback("Drag each sign into the right category box.", "info");
    renderCurrentSign();

    if (state.isCompleted) {
      refs.gameCard.classList.add("is-disabled");
      refs.sign.draggable = false;
      refs.skipBtn.disabled = true;
      setFeedback("Conveyor Mania is locked until new signage is added by the administrator.", "info");
    }

    const initialActive = document.getElementById("view-conveyor")?.classList.contains("active");
    activateSection(Boolean(initialActive));

    window.addEventListener("learnTab:sectionChanged", (event) => {
      const activeSection = event?.detail?.sectionId === "conveyor";
      activateSection(activeSection);
    });
  }

  document.addEventListener("DOMContentLoaded", init);
})();