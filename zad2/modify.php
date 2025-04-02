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
    const bigForm = document.getElementById('bigForm');

    // Premenná pre určenie typu záznamu: 'person', 'organisation' alebo 'both'
    let recordType = 'both';

    // Upravená funkcia pre nastavenie viditeľnosti, ktorá už nepodlieha zmenám od používateľa
    function applyRecordType() {
      if (recordType === 'person') {
        document.getElementById('fullname-container').style.display = 'block';
        document.getElementById('organisation-container').style.display = 'none';
        document.getElementById('gender-container').style.display = 'block';
      } else if (recordType === 'organisation') {
        document.getElementById('fullname-container').style.display = 'none';
        document.getElementById('organisation-container').style.display = 'block';
        document.getElementById('gender-container').style.display = 'none';
      } else {
        // Ak recordType je 'both', dynamické správanie – ak používateľ zadá niečo do jedného z polí, príslušné pole sa ukáže/skryje
        const fullname = document.getElementById('fullname').value;
        const organisation = document.getElementById('organisation').value;
        
        if (fullname && fullname.trim() !== "") {
          document.getElementById('fullname-container').style.display = 'block';
          document.getElementById('organisation-container').style.display = 'none';
          document.getElementById('gender-container').style.display = 'block';
        } else if (organisation && organisation.trim() !== "") {
          document.getElementById('fullname-container').style.display = 'none';
          document.getElementById('organisation-container').style.display = 'block';
          document.getElementById('gender-container').style.display = 'none';
        } else {
          document.getElementById('fullname-container').style.display = 'block';
          document.getElementById('organisation-container').style.display = 'block';
          document.getElementById('gender-container').style.display = 'block';
        }
      }
    }

    // Načítanie údajov o laureátovi a nastavenie režimu (recordType)
    if (!laureateId) {
      laureateMessageDiv.innerHTML = '<div class="message error">Nebolo zadané ID laureáta.</div>';
    } else {
      fetch(`/zad2/api/v0/laureates/${laureateId}`)
        .then(response => {
          if (!response.ok) {
            throw new Error('Laureát sa nenašiel.');
          }
          return response.json();
        })
        .then(data => {
          // Určíme recordType podľa načítaných údajov:
          // Ak je fullname neprázdne, ide o osobu; ak je prázdne a organizácia nie, ide o organizáciu;
          // inak ponecháme 'both'.
          if (data.fullname && data.fullname.trim() !== "") {
            recordType = 'person';
            document.getElementById('fullname').value = data.fullname || '';
          } else if (data.organisation && data.organisation.trim() !== "") {
            recordType = 'organisation';
            document.getElementById('organisation').value = data.organisation;
          } else {
            recordType = 'both';
            document.getElementById('fullname').value = data.fullname || '';
            document.getElementById('organisation').value = data.organisation || '';
          }
          // Naplnenie ostatných polí
          document.getElementById('gender').value = data.sex || '';
          document.getElementById('birth_year').value = data.birth_year || '';
          document.getElementById('death_year').value = data.death_year || '';
          // Aplikujeme režim, ktorý uzamkne príslušné polia
          applyRecordType();
        })
        .catch(error => {
          laureateMessageDiv.innerHTML = `<div class="message error">${error.message}</div>`;
        });
    }

    // Submit veľkého formulára – odoslanie PUT požiadavky
    bigForm.addEventListener('submit', function(e) {
      e.preventDefault();
      if (!laureateId) return;

      // Vyčistenie starých správ
      laureateMessageDiv.innerHTML = '';

      // Zostavenie payloadu
      const formData = new FormData(bigForm);
      const payload = {
        // Kľúče podľa očakávania servera:
        birth_year: parseInt(formData.get('birth_year')) || null,
        death_year: parseInt(formData.get('death_year')) || null
      };

      // Podľa recordType nastavíme buď meno, alebo organizáciu
      if (recordType === 'person') {
        payload.fullname = formData.get('fullname');
        payload.sex = formData.get('gender') || null;
      } else if (recordType === 'organisation') {
        payload.organisation = formData.get('organisation');
      } else {
        // V prípade 'both'
        payload.fullname = formData.get('fullname');
        payload.sex = formData.get('gender') || null;
        payload.organisation = formData.get('organisation');
      }

      // Odošleme PUT požiadavku na server
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
