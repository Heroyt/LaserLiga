import { ArenaFoundUser, searchArenaUsers, updateArenaUser } from "../../api/endpoints/admin";
import { triggerNotification, triggerNotificationError } from "../../components/notifications";
import { startLoading, stopLoading } from "../../loaders";
import { Arena } from "../../interfaces/arena";
import { UserType } from "../../interfaces/users";

declare global {
    const successMessage : string;
    // @ts-ignore
    const arenaId: number;
    const managedArenas: Arena[];
    const managedUserTypes: UserType[];
}

export default function initArenaUsers() {
    const searchInput = document.getElementById("user-search") as HTMLInputElement;
    const userList = document.getElementById("user-list") as HTMLTableSectionElement;
    if (!searchInput || !userList) {
        console.error("Required elements not found on the page");
        return;
    }

    // Convert managedArenas and managedUserTypes to maps
    const managedArenasMap = new Map<number, Arena>(managedArenas.map(arena => [arena.id, arena]));
    const managedUserTypesMap = new Map<number, UserType>(managedUserTypes.map(userType => [userType.id, userType]));

    const foundUsers = new Map<number, UserRow>();

    let debounceTimeout: NodeJS.Timeout | null = null;

    async function searchUsers() {
        startLoading(true);
        const searchValue = searchInput.value.trim();
        try {
            const response = await searchArenaUsers(arenaId, searchValue);
            const notFoundIds = new Set<number>(foundUsers.keys());
            for (const arenaFoundUser of response) {
                notFoundIds.delete(arenaFoundUser.id);
                if (!foundUsers.has(arenaFoundUser.id)) {
                    const userRow = new UserRow(arenaFoundUser, managedArenasMap, managedUserTypesMap);
                    foundUsers.set(arenaFoundUser.id, userRow);
                    userList.appendChild(userRow.row);
                }
            }
            // Remove users that were not found in the current search
            for (const id of notFoundIds) {
                const userRow = foundUsers.get(id);
                if (userRow) {
                    userList.removeChild(userRow.row);
                    foundUsers.delete(id);
                }
            }
            stopLoading(true, true);
        } catch (error) {
            await triggerNotificationError(error);
            stopLoading(false, true);
            return;
        }
    }

    searchInput.addEventListener("input", () => {
        if (debounceTimeout) {
            clearTimeout(debounceTimeout);
        }
        debounceTimeout = setTimeout(searchUsers, 500);
    });

    searchUsers();
}

class UserRow {

    id: number;
    info: ArenaFoundUser;
    row: HTMLTableRowElement;
    managedArenas: Map<number, Arena>;
    managedUserTypes: Map<number, UserType>;
    userRoleSelect: HTMLSelectElement;
    arenasCheckboxes: Map<number, HTMLInputElement> = new Map();

    private debounceTimeout: NodeJS.Timeout | null = null;

    constructor(info: ArenaFoundUser, managedArenas: Map<number, Arena>, managedUserTypes: Map<number, UserType>) {
        this.id = info.id;
        this.info = info;
        this.managedArenas = managedArenas;
        this.managedUserTypes = managedUserTypes;
        this.row = document.createElement("tr");
        this.row.dataset.userId = this.id.toString();

        const nameCell = document.createElement("td");
        nameCell.textContent = info.name;
        this.row.appendChild(nameCell);

        const codeCell = document.createElement("td");
        codeCell.textContent = info.code;
        this.row.appendChild(codeCell);

        const emailCell = document.createElement("td");
        emailCell.textContent = info.email ?? "N/A";
        this.row.appendChild(emailCell);

        const roleCell = document.createElement("td");

        this.userRoleSelect = document.createElement("select");
        this.userRoleSelect.classList.add("form-select");
        if (info.canManage) {
            for (const [id, managedUserType] of managedUserTypes) {
                const option = document.createElement("option");
                option.value = id.toString();
                option.textContent = managedUserType.name;
                if (id === info.userType.id) {
                    option.selected = true;
                }
                this.userRoleSelect.appendChild(option);
            }
            this.userRoleSelect.value = info.userType.id.toString();
        } else {
            this.userRoleSelect.disabled = true;
            const option = document.createElement("option");
            option.value = info.userType.id.toString();
            option.textContent = info.userType.name;
            this.userRoleSelect.appendChild(option);
        }
        roleCell.appendChild(this.userRoleSelect);
        this.row.appendChild(roleCell);
        this.userRoleSelect.addEventListener("change", () => {this.update();})

        const arenasCell = document.createElement("td");

        if (info.canManage) {
            for (const [id, arena] of managedArenas) {
                const wrapper = document.createElement("div");
                wrapper.classList.add("form-check", "form-switch");
                const checkbox = document.createElement("input");
                checkbox.type = "checkbox";
                checkbox.classList.add("form-check-input");
                checkbox.value = id.toString();
                checkbox.id = `arena-${id}-user-${info.id}`;
                checkbox.checked = info.managedArenas.filter(a => a.id === id).length > 0;
                this.arenasCheckboxes.set(id, checkbox);
                const label = document.createElement("label");
                label.classList.add("form-check-label");
                label.setAttribute("for", checkbox.id);
                label.textContent = arena.name;
                wrapper.appendChild(checkbox);
                wrapper.appendChild(label);
                arenasCell.appendChild(wrapper);
                checkbox.addEventListener("change", () => {this.update();})
            }
        }
        for (const managedArena of info.managedArenas) {
            if (this.arenasCheckboxes.has(managedArena.id)) {
                continue;
            }
            const wrapper = document.createElement("div");
            wrapper.classList.add("form-check", "form-switch");
            const checkbox = document.createElement("input");
            checkbox.type = "checkbox";
            checkbox.classList.add("form-check-input");
            checkbox.value = managedArena.id.toString();
            checkbox.id = `arena-${managedArena.id}-user-${info.id}`;
            checkbox.checked = true;
            checkbox.disabled = true;
            this.arenasCheckboxes.set(managedArena.id, checkbox);
            const label = document.createElement("label");
            label.classList.add("form-check-label");
            label.setAttribute("for", checkbox.id);
            label.textContent = managedArena.name;
            wrapper.appendChild(checkbox);
            wrapper.appendChild(label);
            arenasCell.appendChild(wrapper);
        }

        this.row.appendChild(arenasCell);
    }

    update() {
        if (this.debounceTimeout) {
            clearTimeout(this.debounceTimeout);
        }
        this.debounceTimeout = setTimeout(async () => {
            const selectedRoleId = parseInt(this.userRoleSelect.value);
            const selectedArenas = Array.from(this.arenasCheckboxes.values())
                .filter(checkbox => checkbox.checked)
                .map(checkbox => parseInt(checkbox.value));

            try {
                startLoading(true);
                await updateArenaUser(
                    arenaId,
                    this.id,
                    {
                        userTypeId: selectedRoleId,
                        managedArenaIds: selectedArenas
                    }
                );
                stopLoading(true, true);
                triggerNotification({
                    type: "success",
                    content: successMessage
                })
            } catch (error) {
                stopLoading(false, true);
                await triggerNotificationError(error);
            }
        }, 500);
    }

}