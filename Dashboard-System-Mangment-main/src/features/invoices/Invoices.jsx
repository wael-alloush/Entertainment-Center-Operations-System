import React from "react";
import TableActive from "./copmponents/TableActive";
import Tablecompleted from "./copmponents/Tablecompleted";

const Invoices = ({ currentpage, sidebarcollapsed }) => {
  return (
    <div className="">
      {currentpage === "actives" ? (
        <TableActive sidebarcollapsed={sidebarcollapsed} />
      ) : (
        <Tablecompleted />
      )}
    </div>
  );
};

export default Invoices;
