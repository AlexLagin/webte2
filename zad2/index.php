<!DOCTYPE html>
<html lang="sk">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Prehľad laureátov</title>

  <!-- Bootstrap 5 CSS (CDN) -->
  <link
    href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css"
    rel="stylesheet"
  />

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
    /* Klikateľné meno – bez podčiarknutia, so svetlejším hover efektom */
    a.name-link {
      color: inherit;
      text-decoration: none;
      display: inline-block;
      padding: 2px 4px;
      border-radius: 4px;
      transition: background-color 0.2s ease;
      cursor: pointer;
    }
    a.name-link:hover {
      background-color: #f0f0f0;
      text-decoration: underline;
    }
    /* Tlačidlo pre trojbodky (dropdown) – väčšie, s vlastným hover efektom */
    .dots-button {
      font-size: 25px;
      letter-spacing: 0.1em;
    }
    .dots-button:hover {
      color: inherit;
      text-decoration: underline;
      transition: background-color 0.2s ease, color;
    }
  </style>
</head>
<body>

  <!-- Bootstrap Navbar -->
  <nav class="navbar navbar-dark bg-secondary">
    <div class="container-fluid">
      <a class="navbar-brand" href="#">Prehľad laureátov</a>
      <a class="navbar-brand" href="login.php"> Login</a>
    </div>
  </nav>

  <!-- "Papier" karta s tieňom -->
  <div class="container my-5">
    <div class="card shadow">
      <div class="card-body p-4">
        <h1 class="mb-4">Laureates</h1>

        <!-- Riadok s filtermi a tlačidlami -->
        <div class="row align-items-center mb-3">
          <div class="col-md-9">
            <div class="row g-3 align-items-center">
              <div class="col-auto">
                <label for="filterYear" class="col-form-label">Rok:</label>
              </div>
              <div class="col-auto">
                <select id="filterYear" class="form-select form-select-sm">
                  <option value="">Všetky</option>
                </select>
              </div>
              <div class="col-auto">
                <label for="filterCategory" class="col-form-label">Kategória:</label>
              </div>
              <div class="col-auto">
                <select id="filterCategory" class="form-select form-select-sm">
                  <option value="">Všetky</option>
                </select>
              </div>
              <div class="col-auto">
                <label for="filterCountry" class="col-form-label">Krajina:</label>
              </div>
              <div class="col-auto">
                <input type="text" id="filterCountry" class="form-control form-control-sm" placeholder="Hľadať krajinu">
              </div>
            </div>
          </div>
          <div class="col-md-3 text-end">
            <!-- Nové tlačidlo Add more -->

            <a href="addLaureate.php" class="btn btn-outline-primary btn-sm">Add Laureate</a>
          </div>
        </div>

        <!-- Tabuľka -->
        <div class="table-responsive">
          <table class="table table-striped mb-0">
            <thead class="table-light">
              <tr>
                <th>Full Name</th>
                <th>Year</th>
                <th>Category</th>
                <th>Krajina</th>
                <th>Gender</th>
                <th>Birth Year</th>
                <th>Death Year</th>
                <th class="text-center"></th>
              </tr>
            </thead>
            <tbody id="laureates-body">
              <!-- Dynamicky vložené riadky -->
            </tbody>
          </table>
        </div>

        <!-- Spodný panel (footer) so stránkovaním -->
        <div class="mt-3">
          <div class="row align-items-center">
            <!-- Ľavá strana: Rows per page -->
            <div class="col-md-3 d-flex align-items-center">
              <span class="me-2">Rows per page:</span>
              <select id="rowsPerPageSelect" class="form-select form-select-sm" style="width: auto;">
                <option value="5">5</option>
                <option value="10" selected>10</option>
                <option value="20">20</option>
              </select>
            </div>
            <!-- Stred: info o počte záznamov a combobox na výber strany -->
            <div class="col-md-5 text-center">
              <span id="pagination-info" class="text-muted" style="cursor: pointer; text-decoration: underline;"
                    title="Vyber stranu z rozbaľovacieho zoznamu"></span>
              <select id="pageSelect" class="form-select form-select-sm" style="width: auto; display: inline-block; margin-left: 10px;"></select>
            </div>
            <!-- Pravá strana: Tlačidlá Previous/Next -->
            <div class="col-md-4 text-end">
              <button id="prevPageBtn" class="btn btn-outline-primary btn-sm me-2">Previous</button>
              <button id="nextPageBtn" class="btn btn-outline-primary btn-sm">Next</button>
            </div>
          </div>
        </div>
      </div> <!-- /card-body -->
    </div> <!-- /card -->
  </div> <!-- /container -->

  <!-- Modal pre zobrazenie cien -->
  <div class="modal fade" id="prizeModal" tabindex="-1" aria-labelledby="prizeModalLabel" aria-hidden="true">
    <div class="modal-dialog">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title" id="prizeModalLabel">Detaily nobelových cien</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body" id="prizeModalBody">
          <!-- Dynamicky vložené informácie o cenách -->
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-outline-primary btn-sm" data-bs-dismiss="modal">Zavrieť</button>
        </div>
      </div>
    </div>
  </div>

  <!-- Modal pre potvrdenie vymazania -->
  <div class="modal fade" id="deleteModal" tabindex="-1" aria-labelledby="deleteModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title" id="deleteModalLabel">Potvrdenie vymazania</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
          <p>Skutočne chcete vymazať tohto laureáta?</p>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Zrušiť</button>
          <button type="button" id="confirmDeleteBtn" class="btn btn-danger btn-sm">Vymazať</button>
        </div>
      </div>
    </div>
  </div>

  <!-- Bootstrap 5 JS (CDN) -->
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

  <script>
    // Funkcia pre načítanie filter možností z API
    async function fetchFilterOptions() {
      try {
        // Načítame distinct roky
        const yearsResponse = await fetch('/zad2/api/v0/prizes/years');
        if (!yearsResponse.ok) throw new Error('Chyba pri načítaní rokov');
        const years = await yearsResponse.json();
        const filterYear = document.getElementById('filterYear');
        filterYear.innerHTML = '<option value="">Všetky</option>';
        years.forEach(year => {
          const option = document.createElement('option');
          option.value = year;
          option.textContent = year;
          filterYear.appendChild(option);
        });

        // Načítame distinct kategórie
        const catResponse = await fetch('/zad2/api/v0/prizes/categories');
        if (!catResponse.ok) throw new Error('Chyba pri načítaní kategórií');
        const categories = await catResponse.json();
        const filterCategory = document.getElementById('filterCategory');
        filterCategory.innerHTML = '<option value="">Všetky</option>';
        categories.forEach(category => {
          const option = document.createElement('option');
          option.value = category;
          option.textContent = category;
          filterCategory.appendChild(option);
        });
      } catch (error) {
        console.error('Error fetching filter options:', error);
      }
    }

    // Premenné pre stránkovanie
    let currentPage = 1;
    let limit = 10;
    let total = 0;
    let lastPage = 1;

    // DOM referencie
    const tableBody = document.getElementById('laureates-body');
    const rowsPerPageSelect = document.getElementById('rowsPerPageSelect');
    const paginationInfo = document.getElementById('pagination-info');
    const prevPageBtn = document.getElementById('prevPageBtn');
    const nextPageBtn = document.getElementById('nextPageBtn');
    const pageSelect = document.getElementById('pageSelect');

    // Premenná pre ID laureáta, ktorý chceme vymazať
    let deleteLaureateId = null;
    const deleteModal = new bootstrap.Modal(document.getElementById('deleteModal'));

    // Funkcia pre načítanie a zobrazenie laureátov s filtrom
    async function fetchAndDisplayLaureates(page = 1, limit = 10) {
      try {
        // Získame aktuálne hodnoty z filtrov
        const filterYear = document.getElementById('filterYear').value;
        const filterCategory = document.getElementById('filterCategory').value;
        const filterCountry = document.getElementById('filterCountry').value;

        // Zostavíme query string s parametrami
        let query = `?page=${page}&limit=${limit}`;
        if (filterYear) query += `&year=${encodeURIComponent(filterYear)}`;
        if (filterCategory) query += `&category=${encodeURIComponent(filterCategory)}`;
        if (filterCountry) query += `&country=${encodeURIComponent(filterCountry)}`;

        const response = await fetch(`/zad2/api/v0/laureates${query}`);
        if (!response.ok) {
          throw new Error(`HTTP error! status: ${response.status}`);
        }
        const result = await response.json();

        currentPage = result.current_page;
        limit = result.per_page;
        total = result.total;
        lastPage = result.last_page;

        tableBody.innerHTML = '';

        result.data.forEach(laureate => {
          const row = document.createElement('tr');

          // Full Name (klikateľné)
          const nameCell = document.createElement('td');
          const nameLink = document.createElement('a');
          nameLink.href = '#';
          nameLink.classList.add('name-link');
          nameLink.textContent = laureate.fullname ? laureate.fullname : (laureate.organisation || 'Unknown');
          nameLink.addEventListener('click', () => {
            showPrizesModal(laureate.id);
          });
          nameCell.appendChild(nameLink);
          row.appendChild(nameCell);

          // Rok
          const yearCell = document.createElement('td');
          yearCell.textContent = laureate.year || 'N/A';
          row.appendChild(yearCell);

          // Kategória
          const categoryCell = document.createElement('td');
          categoryCell.textContent = laureate.category || 'N/A';
          row.appendChild(categoryCell);

          // Krajina
          const countryCell = document.createElement('td');
          countryCell.textContent = laureate.country || 'N/A';
          row.appendChild(countryCell);

          // Gender
          const sexCell = document.createElement('td');
          sexCell.textContent = laureate.sex ?? 'N/A';
          row.appendChild(sexCell);

          // Birth Year
          const birthCell = document.createElement('td');
          birthCell.textContent = laureate.birth_year || 'N/A';
          row.appendChild(birthCell);

          // Death Year
          const deathCell = document.createElement('td');
          deathCell.textContent = laureate.death_year || 'Still alive';
          row.appendChild(deathCell);

          // Akčné tlačidlo (trojbodky)
          const actionsCell = document.createElement('td');
          actionsCell.classList.add('text-center');
          actionsCell.innerHTML = `
            <div class="dropdown" style="display: inline-block;">
              <button class="btn btn-link p-0 text-decoration-none dots-button" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                ...
              </button>
              <ul class="dropdown-menu dropdown-menu-end">
                <li><a class="dropdown-item edit-link" href="#">Upraviť</a></li>
                <li><a class="dropdown-item delete-link" href="#">Vymazať</a></li>
              </ul>
            </div>
          `;
          row.appendChild(actionsCell);

          const editLink = actionsCell.querySelector('.edit-link');
          editLink.addEventListener('click', () => {
            window.location.href = `modify.php?id=${laureate.id}`;
          });

          const deleteLink = actionsCell.querySelector('.delete-link');
          deleteLink.addEventListener('click', () => {
            deleteLaureateId = laureate.id;
            deleteModal.show();
          });

          tableBody.appendChild(row);
        });

        const startIndex = (currentPage - 1) * limit + 1;
        const endIndex = startIndex + result.data.length - 1;
        paginationInfo.textContent = `${startIndex}-${endIndex} of ${total}`;

        pageSelect.innerHTML = '';
        for (let p = 1; p <= lastPage; p++) {
          const option = document.createElement('option');
          option.value = p;
          option.textContent = p;
          if (p === currentPage) option.selected = true;
          pageSelect.appendChild(option);
        }

        prevPageBtn.disabled = (currentPage <= 1);
        nextPageBtn.disabled = (currentPage >= lastPage);

      } catch (error) {
        console.error('Error fetching laureates:', error);
      }
    }

    // Event listenery pre filtre
    document.getElementById('filterYear').addEventListener('change', () => {
      currentPage = 1;
      fetchAndDisplayLaureates(currentPage, limit);
    });
    document.getElementById('filterCategory').addEventListener('change', () => {
      currentPage = 1;
      fetchAndDisplayLaureates(currentPage, limit);
    });
    document.getElementById('filterCountry').addEventListener('input', () => {
      currentPage = 1;
      fetchAndDisplayLaureates(currentPage, limit);
    });

    // Event listener pre zmenu počtu riadkov
    rowsPerPageSelect.addEventListener('change', () => {
      limit = parseInt(rowsPerPageSelect.value, 10);
      currentPage = 1;
      fetchAndDisplayLaureates(currentPage, limit);
    });

    prevPageBtn.addEventListener('click', () => {
      if (currentPage > 1) {
        currentPage--;
        fetchAndDisplayLaureates(currentPage, limit);
      }
    });

    nextPageBtn.addEventListener('click', () => {
      if (currentPage < lastPage) {
        currentPage++;
        fetchAndDisplayLaureates(currentPage, limit);
      }
    });

    pageSelect.addEventListener('change', () => {
      const selectedPage = parseInt(pageSelect.value, 10);
      if (!isNaN(selectedPage) && selectedPage >= 1 && selectedPage <= lastPage) {
        currentPage = selectedPage;
        fetchAndDisplayLaureates(currentPage, limit);
      }
    });

    async function showPrizesModal(laureateId) {
      try {
        const response = await fetch(`/zad2/api/v0/laureates/${laureateId}/prizes`);
        if (!response.ok) {
          throw new Error(`HTTP error! status: ${response.status}`);
        }
        const prizes = await response.json();
        let contentHtml = '';
        if (!Array.isArray(prizes) || prizes.length === 0) {
          contentHtml = '<p>Tento laureát nemá žiadne ceny.</p>';
        } else {
          contentHtml = prizes.map(prize => {
            const year = prize.year || 'N/A';
            const category = prize.category || 'N/A';
            const contribSK = prize.contrib_sk || 'N/A';
            const contribEN = prize.contrib_en || 'N/A';
            const languageSK = prize.language_sk || 'N/A';
            const languageEN = prize.language_en || 'N/A';
            const genreSK = prize.genre_sk || 'N/A';
            const genreEN = prize.genre_en || 'N/A';

            let prizeHtml = `
              <div class="mb-3">
                <p><strong>Rok:</strong> ${year}</p>
                <p><strong>Kategória:</strong> ${category}</p>
                <p><strong>Ocenenie(SK):</strong> ${contribSK}</p>
                <p><strong>Ocenenie(EN):</strong> ${contribEN}</p>
            `;
            if (category.toLowerCase() === 'literatúra') {
              prizeHtml += `
                <p><strong>Jazyk(SK):</strong> ${languageSK}</p>
                <p><strong>Jazyk(EN):</strong> ${languageEN}</p>
                <p><strong>Žáner(SK):</strong> ${genreSK}</p>
                <p><strong>Žáner(EN):</strong> ${genreEN}</p>
              `;
            }
            prizeHtml += `</div><hr/>`;
            return prizeHtml;
          }).join('');
        }
        document.getElementById('prizeModalBody').innerHTML = contentHtml;
        const modal = new bootstrap.Modal(document.getElementById('prizeModal'));
        modal.show();
      } catch (err) {
        console.error('Error fetching prizes:', err);
      }
    }

    async function deleteLaureate(id) {
      try {
        const response = await fetch(`/zad2/api/v0/laureates/${id}`, {
          method: 'DELETE'
        });
        if (!response.ok) {
          throw new Error(`HTTP error! status: ${response.status}`);
        }
        const result = await response.json();
        console.log(result.message);
        fetchAndDisplayLaureates(currentPage, limit);
      } catch (error) {
        console.error('Error deleting laureate:', error);
      }
    }

    document.getElementById('confirmDeleteBtn').addEventListener('click', () => {
      if (deleteLaureateId !== null) {
        deleteLaureate(deleteLaureateId);
        deleteModal.hide();
      }
    });

    fetchFilterOptions();
    fetchAndDisplayLaureates(currentPage, limit);
  </script>
</body>
</html>
