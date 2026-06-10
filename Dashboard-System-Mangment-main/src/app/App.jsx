import { useState } from "react";

import Sidebar from "../components/layout/Sidebar.jsx";
import Headers from "../components/layout/Header.jsx";
import Dashboard from "../features/Dashboard/Dashboard.jsx";
import Invoices from "../features/invoices/Invoices.jsx";
import Employees from "../features/employees/Employees.jsx";

function App() {
  const [mode, setmode] = useState("");
  const [sidebarcollapsed, setsidebarcollapsed] = useState(false);
  const [currentpage, setcurrentpage] = useState("actives");
  const [currentsubpage, setcurrensubpage] = useState("actives");

  return (
    <div
      className={` ${mode}  min-h-screen bg-base-200 text-base-content transition-all duration-500`}
    >
      <div className="flex h-screen overflow-hidden">
        <Sidebar
          collapsed={sidebarcollapsed}
          ontogglecollapse={() => setsidebarcollapsed(!sidebarcollapsed)}
          currentpage={currentpage}
          onpagechange={setcurrentpage}
          currentsubpage={currentsubpage}
          onsubpagechange={setcurrensubpage}
          setmode={setmode}
        />
        <div className="flex-1 flex flex-col overflow-hidden">
          <Headers
            setmode={setmode}
            mode={mode}
            setsidebarcollapsed={() => setsidebarcollapsed(!sidebarcollapsed)}
          />
          <main className="flex-1 overflow-y-auto bg-transparent ">
            <div className="p-1 sm:p-6 space-y-6 ">
              {currentpage === "dashboard" && <Dashboard />}
              {(currentpage === "actives" || currentpage === "completed") && (
                <Invoices
                  currentpage={currentpage}
                  sidebarcollapsed={sidebarcollapsed}
                />
              )}
              {currentpage === "employees" && <Employees />}
            </div>
          </main>
        </div>
      </div>
    </div>
  );
}

export default App;
