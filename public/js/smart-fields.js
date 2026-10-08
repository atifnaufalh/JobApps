(() => {
  const PROFESSIONS = [
    "Frontend Developer", "Backend Developer", "Fullstack Developer", "Mobile Developer", "DevOps Engineer",
    "Software Engineer", "QA Engineer", "Data Analyst", "Data Scientist", "Data Engineer", "Machine Learning Engineer",
    "Product Manager", "Project Manager", "Scrum Master", "Product Designer", "UI Designer", "UX Researcher",
    "Graphic Designer", "Motion Designer", "Illustrator", "Brand Designer", "Content Writer", "Copywriter",
    "Technical Writer", "Social Media Specialist", "SEO Specialist", "Performance Marketer", "Digital Marketing",
    "Email Marketing Specialist", "Growth Marketer", "Marketing Analyst", "Brand Manager", "Sales Executive",
    "Sales Manager", "Account Executive", "Business Development", "Account Manager", "Customer Service",
    "Customer Success Manager", "Technical Support", "Call Center Agent", "HR Generalist", "Recruiter",
    "Talent Acquisition", "People Operations", "Payroll Officer", "Accountant", "Financial Analyst",
    "Tax Consultant", "Auditor", "Controller", "Warehouse Supervisor", "Logistics Coordinator",
    "Supply Chain Analyst", "Procurement Officer", "Operations Manager", "Admin Staff", "Executive Assistant",
    "Office Manager", "Secretary", "Legal Counsel", "Compliance Officer", "Content Creator", "Video Editor",
    "Photographer", "Journalist", "Editor", "Translator", "Chef", "Barista", "Waiter", "Hotel Staff",
    "Chef de Partie", "Pharmacist", "Nurse", "Doctor", "Laboratory Technician", "Veterinarian",
    "Teacher", "Lecturer", "Tutor", "Curriculum Designer", "Electrical Engineer", "Mechanical Engineer",
    "Civil Engineer", "Industrial Engineer", "Architect", "Interior Designer", "Environmental Consultant",
    "Agricultural Specialist", "Fisheries Officer", "Driver", "Security Guard", "Cleaning Supervisor",
    "Field Surveyor", "GIS Analyst", "Network Engineer", "System Administrator", "Cybersecurity Analyst",
    "Blockchain Developer", "Game Developer", "AR/VR Developer", "Embedded Engineer", "Robotics Engineer",
  ];

  let wilayahPromise = null;
  let uid = 0;

  function loadWilayah() {
    if (!wilayahPromise) {
      wilayahPromise = fetch("/data/wilayah.json", { credentials: "same-origin" })
        .then((response) => {
          if (!response.ok) throw new Error("wilayah unavailable");
          return response.json();
        })
        .then((data) => {
          const seen = new Set();
          const names = [];
          const push = (name) => {
            if (name && !seen.has(name)) {
              seen.add(name);
              names.push(name);
            }
          };
          (data.regencies || []).forEach((item) => push(item.name));
          (data.provinces || []).forEach((item) => push(item.name));
          return names;
        })
        .catch(() => {
          wilayahPromise = null;
          return [];
        });
    }
    return wilayahPromise;
  }

  function escapeHtml(value) {
    return String(value ?? "").replace(/[&<>"']/g, (character) => ({
      "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;", "'": "&#039;",
    })[character]);
  }

  function rank(items, query) {
    const needle = String(query || "").trim().toLowerCase();
    if (!needle) return items.slice(0, 8);
    const starts = [];
    const contains = [];
    for (const item of items) {
      const value = item.toLowerCase();
      if (value.startsWith(needle)) starts.push(item);
      else if (value.includes(needle)) contains.push(item);
      if (starts.length >= 8) break;
    }
    return starts.concat(contains).slice(0, 8);
  }

  function attach(input, getItems) {
    if (input.dataset.acReady) return;
    input.dataset.acReady = "1";
    input.setAttribute("role", "combobox");
    input.setAttribute("aria-autocomplete", "list");
    input.setAttribute("aria-expanded", "false");

    const wrap = document.createElement("div");
    wrap.className = "ac-wrap";
    input.parentNode.insertBefore(wrap, input);
    wrap.appendChild(input);

    const list = document.createElement("ul");
    list.className = "ac-list";
    list.id = `ac-list-${(uid += 1)}`;
    list.setAttribute("role", "listbox");
    list.hidden = true;
    wrap.appendChild(list);
    input.setAttribute("aria-controls", list.id);

    let items = [];
    let active = -1;
    let timer = null;

    const close = () => {
      list.hidden = true;
      list.innerHTML = "";
      input.setAttribute("aria-expanded", "false");
      input.removeAttribute("aria-activedescendant");
      active = -1;
    };

    const open = (matches) => {
      if (!matches.length) return close();
      items = matches;
      list.innerHTML = matches
        .map((match, index) => `<li role="option" id="${list.id}-item-${index}" data-value="${escapeHtml(match)}">${escapeHtml(match)}</li>`)
        .join("");
      list.hidden = false;
      input.setAttribute("aria-expanded", "true");
      active = -1;
      input.removeAttribute("aria-activedescendant");
    };

    const highlight = (index) => {
      if (!items.length) return;
      active = (index + items.length) % items.length;
      list.querySelectorAll("li").forEach((item, position) => item.classList.toggle("ac-active", position === active));
      const current = list.children[active];
      input.setAttribute("aria-activedescendant", current.id);
      current.scrollIntoView?.({ block: "nearest" });
    };

    const commit = (value) => {
      input.value = value;
      close();
      input.dispatchEvent(new Event("input", { bubbles: true }));
      input.dispatchEvent(new Event("change", { bubbles: true }));
    };

    const suggest = () => {
      const value = input.value;
      Promise.resolve(getItems()).then((names) => open(rank(names, value)));
    };

    input.addEventListener("input", () => {
      window.clearTimeout(timer);
      timer = window.setTimeout(suggest, 140);
    });

    input.addEventListener("keydown", (event) => {
      if (event.key === "Escape") {
        close();
        return;
      }
      if (list.hidden) return;
      if (event.key === "ArrowDown") {
        event.preventDefault();
        highlight(active + 1);
      } else if (event.key === "ArrowUp") {
        event.preventDefault();
        highlight(active - 1);
      } else if (event.key === "Enter" && active >= 0) {
        event.preventDefault();
        commit(items[active]);
      }
    });

    list.addEventListener("mousedown", (event) => {
      const option = event.target.closest("li");
      if (!option) return;
      event.preventDefault();
      commit(option.dataset.value);
    });

    input.addEventListener("blur", () => window.setTimeout(close, 120));
  }

  const sources = {
    location: () => loadWilayah(),
    headline: () => Promise.resolve(PROFESSIONS),
  };

  const init = () => {
    document.querySelectorAll("[data-autocomplete]").forEach((input) => {
      const source = sources[input.dataset.autocomplete];
      if (source) attach(input, source);
    });
  };

  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", init);
  } else {
    init();
  }
})();
