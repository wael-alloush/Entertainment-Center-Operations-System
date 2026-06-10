import React, { Children } from "react";
import MyButton from "../../../components/ui/MyButton.jsx";
import { Filter } from "lucide-react";

const FilterSection = ({ filterOpen, children }) => {
  return (
    <div
      className={`${
        filterOpen ? "max-h-96" : "max-h-0 opacity-0 overflow-hidden"
      } transition-all duration-300 ease-in-out`}
    >
      <div className=" relative flex items-center justify-between border border-base-300 mt-4 p-4 rounded-xl">
        <span className="absolute top-0 right-5 -translate-y-[50%] bg-base-100 px-3ش">
          فلاتر البحث
        </span>
        <div className="w-full">
          <div className="flex items-center justify-between flex-wrap gap-4">
            {children}
            <div>
              <MyButton
                title={"مسح الفلاتر"}
                className=" btn-outline  btn-success "
              >
                <Filter className="w-4 h-4 ml-1" />
              </MyButton>
            </div>
          </div>
        </div>
      </div>
    </div>
  );
};

export default FilterSection;
