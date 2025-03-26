<?php
// modify.php
?>
<!DOCTYPE html>
<html lang="sk">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Upraviť laureáta a Nobelove ceny</title>

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
    /* Kontajnery pre meno a organizáciu */
    #fullname-container,
    #organisation-container {
      display: block;
    }
    /* Kontajner pre jazyk a žáner, skrytý pokiaľ kategória nie je "literatúra" */
    .lit-fields {
      display: none;
    }
    /* Štýl pre správu, bez použitia alert tried */
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
  </style>
</head>
<body>
  <!-- Bootstrap Navbar -->
  <nav class="navbar navbar-dark bg-secondary">
    <div class="container-fluid">
      <a class="navbar-brand" href="index.php">Prehľad laureátov</a>
    </div>
  </nav>

  <!-- Karta s formulárom pre úpravu laureáta a jeho cien -->
  <div class="container my-5">
    <div class="card shadow">
      <div class="card-body p-4">
        <h1 class="mb-4">Upraviť laureáta a Nobelove ceny</h1>
        
        <!-- Formulár pre údaje o laureátovi -->
        <form id="modifyLaureateForm">
          <div class="mb-3" id="fullname-container">
            <label for="fullname" class="form-label">Celé meno</label>
            <input type="text" class="form-control" id="fullname" name="fullname">
          </div>
          <div class="mb-3" id="organisation-container">
            <label for="organisation" class="form-label">Organizácia</label>
            <input type="text" class="form-control" id="organisation" name="organisation">
          </div>
          <div class="mb-3">
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
          <button type="submit" class="btn btn-primary">Uložiť zmeny</button>
          <a href="index.php" class="btn btn-secondary">Zrušiť</a>
        </form>
        <div id="laureateMessage"></div>

        <hr>
        <h2>Nobelove ceny</h2>
        <!-- Sekcia pre zobrazenie a úpravu Nobelových cien -->
        <div id="prizes-section"></div>
      </div>
    </div>
  </div>

  <!-- Bootstrap 5 JS (CDN) -->
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
  
  <script>
    // Funkcia na získanie query parametra z URL
    function getQueryParam(name) {
      const urlParams = new URLSearchParams(window.location.search);
      return urlParams.get(name);
    }

    const laureateId = getQueryParam('id');
    const laureateMessageDiv = document.getElementById('laureateMessage');
    const prizesSection = document.getElementById('prizes-section');

    // Aktualizácia viditeľnosti pre "Celé meno" a "Organizácia"
    function updateNameOrgVisibility() {
      const fullname = document.getElementById('fullname').value.trim();
      const organisation = document.getElementById('organisation').value.trim();
      if (fullname !== '') {
        // Ak je vyplnené "Celé meno", skryjeme "Organizáciu"
        document.getElementById('organisation-container').style.display = 'none';
        document.getElementById('fullname-container').style.display = 'block';
      } else if (organisation !== '') {
        // Ak je vyplnená "Organizácia", skryjeme "Celé meno"
        document.getElementById('fullname-container').style.display = 'none';
        document.getElementById('organisation-container').style.display = 'block';
      } else {
        // Ak sú obe prázdne, zobrazíme obe
        document.getElementById('fullname-container').style.display = 'block';
        document.getElementById('organisation-container').style.display = 'block';
      }
    }

    if (!laureateId) {
      laureateMessageDiv.innerHTML = '<div class="message error">Nebolo zadané ID laureáta.</div>';
    } else {
      // Načítame údaje o laureátovi a predvyplníme formulár
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

      document.getElementById('fullname').addEventListener('input', updateNameOrgVisibility);
      document.getElementById('organisation').addEventListener('input', updateNameOrgVisibility);

      // Načítanie Nobelových cien a vytvorenie formulárov pre ich úpravu
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
            prizes.forEach(prize => {
              const prizeForm = document.createElement('form');
              prizeForm.classList.add('mb-4', 'border', 'p-3', 'rounded');
              
              prizeForm.innerHTML = `
                <div class="mb-3">
                  <label class="form-label">Rok</label>
                  <input type="number" class="form-control" name="year" value="${prize.year || ''}">
                </div>
                <div class="mb-3">
                  <label class="form-label">Kategória</label>
                  <input type="text" class="form-control" name="category" value="${prize.category || ''}">
                </div>
                <div class="mb-3">
                  <label class="form-label">Ocenenie (SK)</label>
                  <input type="text" class="form-control" name="contrib_sk" value="${prize.contrib_sk || ''}">
                </div>
                <div class="mb-3">
                  <label class="form-label">Ocenenie (EN)</label>
                  <input type="text" class="form-control" name="contrib_en" value="${prize.contrib_en || ''}">
                </div>
                <div class="lit-fields">
                  <div class="mb-3">
                    <label class="form-label">Jazyk (SK)</label>
                    <input type="text" class="form-control" name="language_sk" value="${prize.language_sk || ''}">
                  </div>
                  <div class="mb-3">
                    <label class="form-label">Jazyk (EN)</label>
                    <input type="text" class="form-control" name="language_en" value="${prize.language_en || ''}">
                  </div>
                  <div class="mb-3">
                    <label class="form-label">Žáner (SK)</label>
                    <input type="text" class="form-control" name="genre_sk" value="${prize.genre_sk || ''}">
                  </div>
                  <div class="mb-3">
                    <label class="form-label">Žáner (EN)</label>
                    <input type="text" class="form-control" name="genre_en" value="${prize.genre_en || ''}">
                  </div>
                </div>
                <button type="submit" class="btn btn-primary">Uložiť zmeny ceny</button>
                <div class="prize-message"></div>
              `;
              prizesSection.appendChild(prizeForm);

              function updateLitFieldsVisibility() {
                const categoryInput = prizeForm.querySelector('input[name="category"]');
                const litFieldsContainer = prizeForm.querySelector('.lit-fields');
                if (categoryInput.value.trim().toLowerCase() === 'literatúra') {
                  litFieldsContainer.style.display = 'block';
                } else {
                  litFieldsContainer.style.display = 'none';
                }
              }

              updateLitFieldsVisibility();
              prizeForm.querySelector('input[name="category"]').addEventListener('input', updateLitFieldsVisibility);

              prizeForm.addEventListener('submit', function(e) {
                e.preventDefault();
                const formData = new FormData(prizeForm);
                const payload = {
                  year: formData.get('year') ? parseInt(formData.get('year')) : null,
                  category: formData.get('category'),
                  contrib_sk: formData.get('contrib_sk'),
                  contrib_en: formData.get('contrib_en'),
                  language_sk: formData.get('language_sk'),
                  language_en: formData.get('language_en'),
                  genre_sk: formData.get('genre_sk'),
                  genre_en: formData.get('genre_en')
                };

                fetch(`/zad2/api/v0/prizes/${prize.id}`, {
                  method: 'PUT',
                  headers: { 'Content-Type': 'application/json' },
                  body: JSON.stringify(payload)
                })
                .then(response => {
                  if (!response.ok) {
                    return response.json().then(err => { 
                      throw new Error(err.message || 'Chyba pri aktualizácii ceny.');
                    });
                  }
                  return response.json();
                })
                .then(result => {
                  prizeForm.querySelector('.prize-message').innerHTML = `<div class="message success">${result.message}</div>`;
                })
                .catch(error => {
                  prizeForm.querySelector('.prize-message').innerHTML = `<div class="message error">${error.message}</div>`;
                });
              });
            });
          }
        })
        .catch(error => {
          prizesSection.innerHTML = `<div class="message error">${error.message}</div>`;
        });
    }

    document.getElementById('modifyLaureateForm').addEventListener('submit', function(e) {
      e.preventDefault();
      if (!laureateId) return;

      let payload = {
        gender: document.getElementById('gender').value,
        birth: parseInt(document.getElementById('birth_year').value) || null,
        death: parseInt(document.getElementById('death_year').value) || null
      };
      if (document.getElementById('fullname-container').style.display !== 'none') {
        payload.fullname = document.getElementById('fullname').value;
      }
      if (document.getElementById('organisation-container').style.display !== 'none') {
        payload.organisation = document.getElementById('organisation').value;
      }

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
