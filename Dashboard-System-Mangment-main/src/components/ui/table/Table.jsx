import React from "react";

const Table = ({ children }) => {
  return (
    <table className="w-full border-separate border-spacing-y-2 border-spacing-x-0 p-1">
      {children}
    </table>
  );
};

export default Table;
