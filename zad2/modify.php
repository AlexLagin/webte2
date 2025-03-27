<?php
// modify.php
?>
<!DOCTYPE html>
<html lang="sk">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Upravovanie nositeľov cien</title>

  <!-- Bootstrap 5 CSS (CDN) -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet" />

  <style>
    body {
      margin: 0;
      padding: 0;
      background-color: #f5f5f5;
    }
    .navbar-brand {
      font-weight: 600;
    }
    .card.shadow {
      margin-top: 20px;
    }
    /* Vonkajší kontajner s okrajom pre celý formulár */
    .inner-form-wrapper {
      border: 1px solid #ccc;
      border-radius: 6px;
      padding: 20px;
    }
    /* Správy bez použitia Bootstrap alert */
    .message {
      padding: 0.5rem;
      margin-top: 0.5rem;
      border: 1px solid #ccc;
      border-radius: 4px;
    }
    .message.success {
      background-color: #d4edda;
    }
    .message.error {
      background-color: #f8d7da;
    }
    /* Skryté polia pre literatúru */
    .lit-fields {
      display: none;
    }
  </style>
</head>
<body>
  <!-- Bootstrap Navbar -->
  <nav class="navbar navbar-dark bg-secondary">
    <div class="container-fluid">
      <a class="navbar-brand" href="index.php">Prehľad laureátov</a>
    </div>
  </nav>

  <div class="container my-5">
    <div class="card shadow">
      <div class="card-body p-4">
        <h1 class="mb-4">Upravovanie nositeľov cien</h1>
        
        <!-- Jeden veľký formulár pre všetko -->
        <form id="bigForm">
          <div class="inner-form-wrapper">
            <!-- Údaje o laureátovi -->
            <div class="mb-3" id="fullname-container">
              <label for="fullname" class="form-label">Celé meno</label>
              <input type="text" class="form-control" id="fullname" name="fullname">
            </div>
            <div class="mb-3" id="organisation-container">
              <label for="organisation" class="form-label">Organizácia</label>
              <input type="text" class="form-control" id="organisation" name="organisation">
            </div>
            <div class="mb-3" id="gender-container">
              <label for="gender" class="form-label">Pohlavie</label>
              <select class="form-select" id="gender" name="gender">
                <option value="">Vyberte...</option>
                <option value="M">Muž</option>
                <option value="F">Žena</option>
              </select>
            </div>
            <div class="mb-3">
              <label for="birth_year" class="form-label">Rok narodenia</label>
              <input type="number" class="form-control" id="birth_year" name="birth_year">
            </div>
            <div class="mb-3">
              <label for="death_year" class="form-label">Rok úmrtia</label>
              <input type="number" class="form-control" id="death_year" name="death_year">
              <div class="form-text">Nechajte prázdne, ak je stále nažive.</div>
            </div>

            <!-- Sekcia pre Nobelove ceny -->
            <div id="prizes-section" class="mt-4">
              <!-- Dynamicky vložené polia pre každú cenu budú mať štruktúru: 
                   prizes[index][id] (hidden), prizes[index][year], prizes[index][category], 
                   prizes[index][contrib_sk], prizes[index][contrib_en],
                   a ak kategória == "literatúra": prizes[index][language_sk], prizes[index][language_en],
                   prizes[index][genre_sk], prizes[index][genre_en] -->
            </div>

            <!-- Tlačidlo Uložiť zmeny (zarovnané na stred) -->
            <div class="text-center mt-4">
              <button type="submit" class="btn btn-outline-primary btn-sm">Uložiť zmeny</button>
            </div>
          </div>
        </form>
        <div id="laureateMessage"></div>
      </div>
    </div>
  </div>

  <!-- Bootstrap 5 JS (CDN) -->
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
  
  <script>
    // Pomocná funkcia na získanie query parametra z URL
    function getQueryParam(name) {
      const urlParams = new URLSearchParams(window.location.search);
      return urlParams.get(name);
    }

    const laureateId = getQueryParam('id');
    const laureateMessageDiv = document.getElementById('laureateMessage');
    const prizesSection = document.getElementById('prizes-section');
    const bigForm = document.getElementById('bigForm');

    // Aktualizácia viditeľnosti pre "Celé meno", "Organizáciu" a "Pohlavie"
    function updateNameOrgVisibility() {
      const fullname = document.getElementById('fullname').value.trim();
      const organisation = document.getElementById('organisation').value.trim();
      
      if (fullname !== '') {
        // Ak je vyplnené "Celé meno", zobrazíme "Celé meno" aj "Pohlavie" a skryjeme "Organizáciu"
        document.getElementById('fullname-container').style.display = 'block';
        document.getElementById('organisation-container').style.display = 'none';
        document.getElementById('gender-container').style.display = 'block';
      } else if (organisation !== '') {
        // Ak je vyplnená "Organizácia", zobrazíme len "Organizáciu" a skryjeme "Celé meno" a "Pohlavie"
        document.getElementById('fullname-container').style.display = 'none';
        document.getElementById('organisation-container').style.display = 'block';
        document.getElementById('gender-container').style.display = 'none';
      } else {
        // Ak sú obe prázdne, zobrazíme obe a aj "Pohlavie"
        document.getElementById('fullname-container').style.display = 'block';
        document.getElementById('organisation-container').style.display = 'block';
        document.getElementById('gender-container').style.display = 'block';
      }
    }

    // Automatické prispôsobenie výšky pre <textarea>
    function autoResizeTextarea(el) {
      el.style.height = 'auto';
      el.style.height = el.scrollHeight + 'px';
    }

    // Ak nebolo zadané ID, zobraz chybu
    if (!laureateId) {
      laureateMessageDiv.innerHTML = '<div class="message error">Nebolo zadané ID laureáta.</div>';
    } else {
      // Načítanie údajov o laureátovi a predvyplnenie formulára
      fetch(`/zad2/api/v0/laureates/${laureateId}`)
        .then(response => {
          if (!response.ok) {
            throw new Error('Laureát sa nenašiel.');
          }
          return response.json();
        })
        .then(data => {
          if (data.organisation && data.organisation.trim() !== '') {
            document.getElementById('organisation').value = data.organisation;
          } else {
            document.getElementById('fullname').value = data.fullname || '';
          }
          document.getElementById('gender').value = data.sex || '';
          document.getElementById('birth_year').value = data.birth_year || '';
          document.getElementById('death_year').value = data.death_year || '';
          updateNameOrgVisibility();
        })
        .catch(error => {
          laureateMessageDiv.innerHTML = `<div class="message error">${error.message}</div>`;
        });

      // Načítanie Nobelových cien a vytvorenie polí pre každú cenu
      fetch(`/zad2/api/v0/laureates/${laureateId}/prizes`)
        .then(response => {
          if (!response.ok) {
            throw new Error('Chyba pri načítaní informácií o cene.');
          }
          return response.json();
        })
        .then(prizes => {
          if (!Array.isArray(prizes) || prizes.length === 0) {
            prizesSection.innerHTML = `<p>Tento laureát nezískal žiadnu cenu.</p>`;
          } else {
            prizes.forEach((prize, index) => {
              const container = document.createElement('div');
              container.classList.add('mb-4');

              const isLiterature = (prize.category || '').trim().toLowerCase() === 'literatúra';

              container.innerHTML = `
                <input type="hidden" name="prizes[${index}][id]" value="${prize.id}">
                <div class="mb-3">
                  <label class="form-label">Rok</label>
                  <input type="number" class="form-control" name="prizes[${index}][year]" value="${prize.year || ''}">
                </div>
                <div class="mb-3">
                  <label class="form-label">Kategória</label>
                  <input type="text" class="form-control prize-category" name="prizes[${index}][category]" value="${prize.category || ''}">
                </div>
                <div class="mb-3">
                  <label class="form-label">Ocenenie (SK)</label>
                  <textarea class="form-control auto-resize" name="prizes[${index}][contrib_sk]" rows="1">${prize.contrib_sk || ''}</textarea>
                </div>
                <div class="mb-3">
                  <label class="form-label">Ocenenie (EN)</label>
                  <textarea class="form-control auto-resize" name="prizes[${index}][contrib_en]" rows="1">${prize.contrib_en || ''}</textarea>
                </div>
                <div class="lit-fields" id="lit-fields-${index}" style="display: ${isLiterature ? 'block' : 'none'};">
                  <div class="mb-3">
                    <label class="form-label">Jazyk (SK)</label>
                    <textarea class="form-control auto-resize" name="prizes[${index}][language_sk]" rows="1">${prize.language_sk || ''}</textarea>
                  </div>
                  <div class="mb-3">
                    <label class="form-label">Jazyk (EN)</label>
                    <textarea class="form-control auto-resize" name="prizes[${index}][language_en]" rows="1">${prize.language_en || ''}</textarea>
                  </div>
                  <div class="mb-3">
                    <label class="form-label">Žáner (SK)</label>
                    <textarea class="form-control auto-resize" name="prizes[${index}][genre_sk]" rows="1">${prize.genre_sk || ''}</textarea>
                  </div>
                  <div class="mb-3">
                    <label class="form-label">Žáner (EN)</label>
                    <textarea class="form-control auto-resize" name="prizes[${index}][genre_en]" rows="1">${prize.genre_en || ''}</textarea>
                  </div>
                </div>
              `;
              prizesSection.appendChild(container);

              // Reagovať na zmenu kategórie – ak sa zmení na "literatúra", zobrazí sa lit-fields
              const categoryInput = container.querySelector('.prize-category');
              categoryInput.addEventListener('input', () => {
                const litContainer = document.getElementById(`lit-fields-${index}`);
                if (categoryInput.value.trim().toLowerCase() === 'literatúra') {
                  litContainer.style.display = 'block';
                } else {
                  litContainer.style.display = 'none';
                }
              });
            });

            // Spusti auto-resize pre všetky <textarea>
            document.querySelectorAll('textarea.auto-resize').forEach((ta) => {
              autoResizeTextarea(ta);
              ta.addEventListener('input', () => autoResizeTextarea(ta));
            });
          }
        })
        .catch(error => {
          laureateMessageDiv.innerHTML = `<div class="message error">${error.message}</div>`;
        });
    }

    // Aktualizácia viditeľnosti polí pri zmene "fullname" a "organisation"
    document.getElementById('fullname').addEventListener('input', updateNameOrgVisibility);
    document.getElementById('organisation').addEventListener('input', updateNameOrgVisibility);

    // Submit veľkého formulára – odošleme všetko v jednej PUT požiadavke
    bigForm.addEventListener('submit', function(e) {
      e.preventDefault();
      if (!laureateId) return;

      // Front-end validácia: ak je viditeľné pole pre "Celé meno" alebo "Organizácia", musíme mať vyplnené aspoň jedno
      laureateMessageDiv.innerHTML = ''; // vyčistiť staré správy
      const fullVal = document.getElementById('fullname').value.trim();
      const orgVal = document.getElementById('organisation').value.trim();

      if (document.getElementById('fullname-container').style.display !== 'none' && !fullVal) {
        laureateMessageDiv.innerHTML = `<div class="message error">Meno musí byť zadané</div>`;
        return;
      }
      if (document.getElementById('organisation-container').style.display !== 'none' && !orgVal) {
        laureateMessageDiv.innerHTML = `<div class="message error">Meno musí byť zadané</div>`;
        return;
      }

      // Zostavenie payloadu
      const formData = new FormData(bigForm);
      const payload = {
        birth: parseInt(formData.get('birth_year')) || null,
        death: parseInt(formData.get('death_year')) || null,
        prizes: []
      };

      if (document.getElementById('fullname-container').style.display !== 'none') {
        payload.fullname = formData.get('fullname');
        payload.gender = formData.get('gender') || null;
      }
      if (document.getElementById('organisation-container').style.display !== 'none') {
        payload.organisation = formData.get('organisation');
      }

      // Spracovanie údajov o cenách
      const prizesMap = {};
      for (let [key, value] of formData.entries()) {
        const match = key.match(/^prizes\[(\d+)\]\[(.+)\]$/);
        if (match) {
          const index = match[1];
          const field = match[2];
          if (!prizesMap[index]) {
            prizesMap[index] = {};
          }
          prizesMap[index][field] = value;
        }
      }
      Object.keys(prizesMap).forEach(index => {
        const p = prizesMap[index];
        payload.prizes.push({
          id: parseInt(p.id),
          year: p.year ? parseInt(p.year) : null,
          category: p.category || null,
          contrib_sk: p.contrib_sk || null,
          contrib_en: p.contrib_en || null,
          language_sk: p.language_sk || null,
          language_en: p.language_en || null,
          genre_sk: p.genre_sk || null,
          genre_en: p.genre_en || null
        });
      });

      // Odošleme PUT požiadavku
      fetch(`/zad2/api/v0/laureates/${laureateId}`, {
        method: 'PUT',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload)
      })
      .then(response => {
        if (!response.ok) {
          return response.json().then(err => {
            throw new Error(err.message || 'Chyba pri aktualizácii.');
          });
        }
        return response.json();
      })
      .then(result => {
        laureateMessageDiv.innerHTML = `<div class="message success">${result.message}</div>`;
      })
      .catch(error => {
        laureateMessageDiv.innerHTML = `<div class="message error">${error.message}</div>`;
      });
    });
  </script>
</body>
</html>
