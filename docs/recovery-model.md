# Biomechanical Fatigue & Muscle Recovery Model

## 1. Overview
The Recovery Interface utilizes an empirical mathematical heuristic to track muscular readiness across 17 anatomical muscle groups. It translates acute training stress into accumulated fatigue and projects biological recovery as an exponential decay function over time.

> **Important:** This model is intended for software-based training visualization
> and planning. It is not a clinical measurement of muscle recovery, and its
> age, sex, and activity coefficients are heuristic modelling assumptions rather
> than medically validated constants.


---

## 2. Mathematical Formulation

### 2.1 Acute Fatigue Accumulation (Per Set)
For each set logged within the 14-day lookback window, acute fatigue contributed to a target muscle is defined as:

$$\text{Fatigue}_{\text{set}} = \text{BaseFatigue} \times \left(\frac{\text{RPE}}{10}\right) \times \text{Activation} \times \text{RelativeLoad}$$

Where:
* **$\text{BaseFatigue} = 12.0$**: Baseline arbitrary fatigue units per standard working set.
* **$\text{RPE}$ (Rate of Perceived Exertion, $1.0 - 10.0$)**: Normalizes exertion based on reps in reserve (RIR). Sets logged without RPE default to $7.0$.
* **$\text{Activation} \in (0.0, 1.0]$**: Muscle involvement coefficient stored in the exercise library.
  * Primary Movers: $0.80 - 1.00$ (e.g., Pectoralis Major in Bench Press = $1.00$).
  * Synergists / Secondary: $0.20 - 0.50$ (e.g., Triceps Brachii in Bench Press = $0.50$).
* **$\text{RelativeLoad}$**: Ratio between the external load lifted and the user's body weight:
  $$\text{RelativeLoad} = \frac{\text{EffectiveWeight}}{\text{BodyWeight}}$$
  * Clamped to $[0.3, 3.5]$ to prevent mathematical distortion from extreme inputs.
  * For bodyweight movements ($\text{is\_bodyweight} = 1$), $\text{EffectiveWeight} = \text{BodyWeight} + \text{AddedWeight}$.
  * Defaults to $1.0$ if no biometric profile is configured.

---

### 2.2 Continuous Exponential Decay Function
Biological recovery does not occur linearly; cellular repair and glycogen resynthesis exhibit high initial velocity that tapers asymptotically. Residual fatigue at elapsed time $t$ (in hours) follows:

$$F(t) = F_0 \cdot \exp\left(-\frac{k \cdot t}{T_{\text{rec}}}\right)$$

Where:
* **$k = 3.0$ (Decay Constant)**: Chosen because $\exp(-3.0) \approx 0.0498$ ($\approx 5\%$). At $t = T_{\text{rec}}$, exactly $95\%$ of initial set fatigue has dissipated.
* **$T_{\text{rec}}$ (Effective Recovery Window)**: Base anatomical recovery hours adjusted by biometric coefficients.

---

### 2.3 Personalized Biometric Modifiers
Effective recovery hours are personalized according to:

$$T_{\text{rec}} = T_{\text{base}} \times M_{\text{activity}} \times M_{\text{sex}} \times M_{\text{age}}$$

1. **Base Anatomical Hours ($T_{\text{base}}$)**:
   * Calves, Forearms, Abs: $24\text{h}$
   * Biceps: $36\text{h}$
   * Chest, Deltoids, Triceps, Traps: $48\text{h}$
   * Lats, Glutes: $60\text{h}$
   * Quads, Hamstrings, Lower Back: $72\text{h}$
2. **Activity Level Multiplier ($M_{\text{activity}}$)**:
   * Sedentary ($1.15$), Lightly Active ($1.05$), Moderately Active ($1.00$), Very Active ($0.90$), Athlete ($0.80$).
3. **Biological Sex Multiplier ($M_{\text{sex}}$)**:
   * Female ($0.95$), Male / Unspecified ($1.00$), reflecting observed differences in substrate utilization and fatigue resistance during submaximal resistance bouts.
4. **Age Multiplier ($M_{\text{age}}$)**:
   * $< 30$: $1.00$
   * $30 - 39$: $1.05$ ($+5\%$ time)
   * $40 - 49$: $1.12$ ($+12\%$ time)
   * $\ge 50$: $1.22$ ($+22\%$ time)

---

### 2.4 Readiness Score & HUD Telemetry Mapping
Total residual fatigue across all active sets is aggregated:

$$\text{Fatigue}_{\text{total}} = \sum_{i=1}^{N} F_i(t_i)$$

$$\text{Readiness} = \operatorname{clamp}\left(0, 100, \operatorname{round}(100 - \text{Fatigue}_{\text{total}})\right)$$

* **$\ge 85\%$ — Fully Recovered** (`#39FF88` Neon Green)
* **$60\% - 84\%$ — Ready** (`#7CE8FF` Cyan)
* **$35\% - 59\%$ — Recovering** (`#FFB347` Amber)
* **$< 35\%$ — Fatigued / Rest Advised** (`#FF5C5C` Red with Telemetric Pulse)

---

## 3. Scope & Known Limitations
This is a heuristic model for workout scheduling and volume allocation. Explicit engineering boundaries include:
* **No Direct Systemic Biomarker Tracking**: Does not measure serum creatine kinase, systemic inflammation, or sleep duration.
* **Systemic / CNS Fatigue**: The current implementation does not contain a dedicated CNS fatigue model. Instead, compound exercises indirectly distribute stress across multiple muscle groups through the activation mapping.
* **Biomechanical Form Invariance**: Assumes standard exercise range of motion and consistent repetition tempo.