document.addEventListener("DOMContentLoaded", () => {
  fetch("registrations.json") 
    .then(response => response.json())
    .then(data => {
      const content = document.getElementById("content");
      const quickLinks = document.getElementById("quickLinks");

      let allUsers = [];

      // Flatten primary + additional into one array of users
      data.forEach(reg => {
        if (reg.primary) {
          allUsers.push({
            ...reg.primary,
            created_at: reg.created_at
          });
        }
        if (reg.additional && reg.additional.length > 0) {
          reg.additional.forEach(user => {
            allUsers.push({
              ...user,
              created_at: reg.created_at
            });
          });
        }
      });

      // Require an email query param to access the page
      const params = new URLSearchParams(window.location.search);
      const emailParam = params.get("email");
      if (!emailParam) {
        content.innerHTML = `<p>Please use your personalized link to view your registration.</p>`;
        return;
      }

      // If an email query param is present, render only that user's card
      if (emailParam) {
        const target = allUsers.find(u => (u.email || "").toLowerCase() === emailParam.toLowerCase());
        if (target) {
          renderUser(target, 1, content);
        } else {
          content.innerHTML = `<p>No registration found for this email.</p>`;
        }
        return;
      }

      // Get hash if present (e.g., #user-3)
      const hash = window.location.hash;
      let userIndex = null;
      if (hash && hash.startsWith("#user-")) {
        userIndex = parseInt(hash.replace("#user-", ""), 10) - 1;
      }

      // If a specific user is requested, show only that one
      if (userIndex !== null && allUsers[userIndex]) {
        renderUser(allUsers[userIndex], userIndex + 1, content);
      } else {
        // Otherwise show all + quick links
        if (allUsers.length > 0) quickLinks.style.display = "block";
        allUsers.forEach((user, index) => {
          renderUser(user, index + 1, content);

          const link = document.createElement("a");
          link.href = `#user-${index + 1}`;
          link.textContent = `User #${index + 1}`;
          link.style.marginRight = "15px";
          quickLinks.appendChild(link);
        });
      }
    })
    .catch(err => console.error("Error loading registrations.json:", err));
});

// helper function
function renderUser(user, index, container) {
  const section = document.createElement("div");
  section.className = "registration-section";
  section.id = `user-${index}`;

  section.innerHTML = `
    <h2 class="reg-header">${user.firstName || user.name || ""} ${user.surname || ""}</h2>
    <div class="contact-info">
      <p><strong>Email:</strong> ${user.email}</p>
      <p><strong>Phone:</strong> ${user.phone}</p>
      <p><strong>Organization:</strong> ${user.organization || "N/A"}</p>
      <p><strong>Position:</strong> ${user.position || "N/A"}</p>
      <p><strong>Category:</strong> ${user.category || "N/A"}</p>
      <p><strong>Participant Type:</strong> ${user.participantType || "N/A"}</p>
      <p><strong>Sub-theme:</strong> ${user.subTheme || "N/A"}</p>
    </div>
  `;

  container.appendChild(section);
}
