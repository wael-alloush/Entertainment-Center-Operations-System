import React from "react";
import MyButton from "../../../components/ui/MyButton";
import { Filter, RefreshCw } from "lucide-react";

const HeaderOfTable = ({
  children,
  badge,
  title,
  filterOpen,
  setFilterOpen,
}) => {
  return (
    <div className="py-2 md:p-6 border-b border-base-300">
      <div className="flex items-center justify-between">
        <div className="flex space-x-4 space-y-2 items-baseline flex-wrap">
          <h3 className="text-lg font-bold ">{title}</h3>
          {badge}
        </div>
        <div className="flex items-center space-x-4">
          <MyButton
            onClick={() => {
              setFilterOpen(!filterOpen);
            }}
            className=" btn-outline btn-info p-3 "
          >
            <Filter className="w-4 h-4" />
          </MyButton>
          <MyButton
            // onClick={}
            className="  btn-info  text-info-content  p-3"
          >
            <RefreshCw className="w-4 h-4" />
          </MyButton>
        </div>
      </div>
      {children}
    </div>
  );
};

export default HeaderOfTable;
